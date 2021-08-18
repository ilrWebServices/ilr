<?php

namespace Drupal\collegenet2sf;

use Drupal\sftp_client\SftpClientInterface;
use League\Csv\Reader;
use League\Csv\Statement;
use League\Csv\Writer;
use Drupal\salesforce\Rest\RestClientInterface;
use Drupal\salesforce\SelectQuery as SalesforceSelectQuery;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Psr\Log\LoggerInterface;

/**
 * Fetches remote CollegeNET data and bulk upserts it to Salesforce.
 */
class CollegeNetToSalesforceProcessor {

  /**
   * User defined exception to prevent items from being added to the queue.
   */
  const INTERNAL_EXCEPTION = 1;

  /**
   * The sftp_client service.
   *
   * @var \Drupal\sftp_client\SftpClientInterface
   */
  protected $sftpClient;

  /**
   * Salesforce client.
   *
   * @var \Drupal\salesforce\Rest\RestClientInterface
   */
  protected $sfapi;

  /**
   * The collegenet_lead_queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Field mappings from the CollegeNET to Salesforce field names.
   *
   * @var array
   */
  protected $mapping = [];

  /**
   * SFTP settings.
   *
   * @var array
   */
  protected $sftpSettings = [];

  /**
   * Default fields to add to Leads.
   *
   * @var array
   */
  protected $defaultFields = [];

  /**
   * The name of the Salesforce field to use as an external ID for CRM_ID.
   *
   * @var string
   */
  protected $externalId = '';

  /**
   * A list of unlinked Grad Leads missing an external ID.
   *
   * Items are SOQL query result records.
   *
   * @var array
   */
  private $unlinkedLeads = [];

  /**
   * A list of unlinked Grad Leads to link before the batch runs.
   *
   * Keys are Salesforce IDs and values are external IDs (CollegeNET CRM ID).
   *
   * @todo Confirm usage is OK if service is used multiple times in a request.
   *
   * @var array
   */
  private $leadsToLink = [];

  /**
   * Constructs this CollegeNetToSalesforceProcessor controller.
   *
   * @param \Drupal\sftp_client\SftpClientInterface $sftp_client
   *   The sftp_client service.
   * @param \Drupal\salesforce\Rest\RestClientInterface $sfapi
   *   Salesforce service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(SftpClientInterface $sftp_client, RestClientInterface $sfapi, ConfigFactoryInterface $config_factory, QueueFactory $queue_factory, LoggerInterface $logger) {
    $this->sftpClient = $sftp_client;
    $this->sfapi = $sfapi;
    $this->queue = $queue_factory->get('collegenet_bulk_job');
    $this->logger = $logger;

    // Load and set settings with default values.
    $settings = $config_factory->get('collegenet2sf.settings');
    $sftp_settings = $settings->get('sftp') ?? [];
    $default_fields = $settings->get('default_fields') ?? [];
    $this->mapping = $settings->get('mapping') ?? [];
    $this->sftpSettings = $sftp_settings + [
      'connection_name' => 'collegenet',
      'filename_pattern' => '/.*\.csv$/',
      'file_max_age_days' => 365,
      'report_directory' => '/',
    ];

    // We do this because Drupal config YAML does not allow '.' (dot) characters
    // in keys.
    $this->defaultFields = array_combine(
      array_column($default_fields, 'key'),
      array_column($default_fields, 'value')
    );

    $this->externalId = $this->mapping['CRM_ID'] ?? '';
  }

  /**
   * Executes a CollegeNET data fetch and bulk upload to Salesforce.
   *
   * @return bool
   *   TRUE upon success, FALSE otherwise.
   */
  public function run() {
    if (empty($this->externalId)) {
      $this->logger->error('No mapping set for CRM_ID. This is required for the external Salesforce ID used for upserts.');
      return FALSE;
    }

    try {
      // Load the remote CSV file data.
      $csv_data = $this->loadData();
    }
    catch (\Exception $e) {
      if ($e->getCode() === self::INTERNAL_EXCEPTION) {
        $this->logger->info('CollegeNet file not processed: @message', [
          '@message' => $e->getMessage(),
        ]);
      }
      else {
        $this->logger->error('CollegeNet data load error: @message', [
          '@message' => $e->getMessage(),
        ]);
      }

      return FALSE;
    }

    try {
      // Parse the CSV data.
      $reader = Reader::createFromString($csv_data);
      $reader->setHeaderOffset(0);
    }
    catch (\Exception $e) {
      $this->logger->error('CollegeNet data CSV read error: @message', [
        '@message' => $e->getMessage(),
      ]);

      return FALSE;
    }

    // No records in the export? Nothing further to do.
    if ($reader->count() === 0) {
      $this->logger->info('CollegeNet record count is zero.');
      return FALSE;
    }

    try {
      // Get records with a value for CRM_ID. We assume that these are
      // applications.
      // @todo Remove this since the report already ensures that all records have a CRM_ID?
      $applications = $this->filterApplications($reader);
    }
    catch (\Exception $e) {
      $this->logger->error('CollegeNet data CSV application filter error: @message', [
        '@message' => $e->getMessage(),
      ]);

      return FALSE;
    }

    try {
      // Get all Grad Leads from Salesforce that do not have a value for the
      // CollegeNET ID.
      $this->fetchUnlinkedLeads();
    }
    catch (\Exception $e) {
      $this->logger->error('CollegeNet Salesforce duplicate Lead check error: @message', [
        '@message' => $e->getMessage(),
      ]);

      return FALSE;
    }

    try {
      // Prepare the bulk upsert application data.
      $writer = Writer::createFromString();

      $default_field_columns = array_keys($this->defaultFields);
      $default_field_values = array_values($this->defaultFields);

      // Set the header column names, adding any default columns.
      $writer->insertOne(array_merge(array_values($this->mapping), $default_field_columns));

      foreach ($applications->getRecords() as $rownum => $record) {
        $new_record = [];

        // Creating a new record in the order of the mapping will ensure that
        // the columns and fields match, even if the source file changes the
        // order of the columns.
        foreach ($this->mapping as $collegenet_key => $salesforce_fieldname) {
          $new_record[$salesforce_fieldname] = $record[$collegenet_key] ?? '';
        }

        // Add the record to the new CSV file, adding any values default fields.
        $writer->insertOne($new_record + $default_field_values);

        // Check if an unlinked Lead for this email exists. If so, we'll need to
        // link the Lead before running the batch.
        if ($unlinkedSfid = $this->getUnlinkedLeadByEmail($new_record['Email'])) {
          $this->leadsToLink[$unlinkedSfid] = $new_record[$this->externalId];
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->error('CollegeNet data CSV write error: @message', [
        '@message' => $e->getMessage(),
      ]);

      return FALSE;
    }

    // Link any unlinked Leads to ensure that they have external IDs for the
    // later batch upsert.
    try {
      $leads_linked = 0;

      foreach ($this->leadsToLink as $sfid_to_update => $external_id) {
        $link_response = $this->sfapi->apiCall("sobjects/Lead/" . $sfid_to_update, [$this->externalId => $external_id], 'PATCH', TRUE);

        // The 204 status code seems to come back from a PATCH request, but any
        // 200 code is fine.
        if ($link_response->getStatusCode() >= 200 && $link_response->getStatusCode() < 300) {
          $leads_linked++;
        }
      }
    }
    catch (\Exception $e) {
      // Log but do not stop processing.
      $this->logger->error('CollegeNet Lead link error: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    if ($leads_linked) {
      $this->logger->info('%count CollegeNet Lead(s) linked.', [
        '%count' => $leads_linked,
      ]);
    }

    try {
      // Create the Bulk API 2.0 job.
      $job_create_response = $this->sfapi->apiCall('jobs/ingest', [
        'object' => 'Lead',
        'externalIdFieldName' => $this->externalId,
        'contentType' => 'CSV',
        'operation' => 'upsert',
      ], 'POST', TRUE);

      $job_id = $job_create_response->data['id'];

      // Upload the json data.
      $this->sfapi->apiCall("jobs/ingest/$job_id/batches", $writer->toString(), 'PUT', TRUE, ['Content-type' => 'text/csv']);

      // Close the job.
      $this->sfapi->apiCall('jobs/ingest/' . $job_id, [
        'state' => 'UploadComplete',
      ], 'PATCH', TRUE);
    }
    catch (\Exception $e) {
      $this->logger->error('CollegeNet Salesforce Bulk API error: @message', [
        '@message' => $e->getMessage(),
      ]);

      return FALSE;
    }

    // Add the job id to the `collegenet_bulk_job` queue.
    $this->queue->createItem($job_id);

    return TRUE;
  }

  /**
   * Load CSV data from the SFTP connection.
   *
   * @return string
   *   Unparsed CSV data from the remote file.
   */
  protected function loadData() {
    $this->sftpClient->setSettings($this->sftpSettings['connection_name']);

    $files = $this->sftpClient->listFiles($this->sftpSettings['report_directory']);

    if ($most_recent_export_file = $this->getMostRecent($this->filter($files))) {
      // Log this data file fetch.
      $this->logger->info('CollegeNet data fetch successful for %filename.', [
        '%filename' => $most_recent_export_file->getFilename(),
      ]);

      $data = $this->sftpClient->readFile($this->sftpSettings['report_directory'] . '/' . $most_recent_export_file->getFilename());

      if (empty($data)) {
        throw new \Exception('Empty file retrieved from CollegeNet: ' . $most_recent_export_file->getFilename(), self::INTERNAL_EXCEPTION);
      }

      return $data;
    }
    else {
      throw new \Exception('Unable to retrieve most recent file from CollegeNet.', self::INTERNAL_EXCEPTION);
    }
  }

  /**
   * Filter CSV Reader records and remove any without an external id.
   *
   * @param \League\Csv\Reader $reader
   *   A CSV Reader object with parsed data.
   *
   * @return \League\Csv\Statement
   *   An iterable \League\Csv\Statement with the filtered records.
   */
  protected function filterApplications(Reader $reader) {
    return Statement::create()
      ->where(fn(array $record) => !empty($record['CRM_ID']))
      ->process($reader);
  }

  /**
   * Fetch all Leads that don't have an external ID.
   *
   * @return bool
   *   TRUE if the records were fetched and stored. FALSE if not.
   */
  protected function fetchUnlinkedLeads() {
    $query = new SalesforceSelectQuery('Lead');
    $query->fields = ['Id', 'Email'];
    $query->addCondition('RecordType.Name', "'Grad'");
    $query->addCondition($this->externalId, 'null');
    $query->order['LastModifiedDate'] = 'DESC';

    $response = $this->sfapi->apiCall('query?q=' . (string) $query, [], 'GET', TRUE);

    if (empty($response->data['totalSize'])) {
      return FALSE;
    }

    $this->unlinkedLeads = $response->data['records'];
    return TRUE;
  }

  /**
   * Get a Salesforce ID for an unlinked Lead for a given email.
   *
   * @param string $email
   *   An email address.
   *
   * @return string|false
   *   The Salesforce ID for the unlinked Lead.
   */
  protected function getUnlinkedLeadByEmail($email) {
    // Since $this->unlinkedLeads is sorted by LastModifiedDate descending, the
    // first result will be the most recently modified.
    $first_match_key = array_search($email, array_column($this->unlinkedLeads, 'Email'));

    if ($first_match_key) {
      return $this->unlinkedLeads[$first_match_key]['Id'];
    }

    return FALSE;
  }

  /**
   * Filter CSV files by file name and age.
   *
   * @param \Traversable $files
   *   A list of files from SftpClientInterface::listFiles().
   *
   * @return \Drupal\sftp_client\SftpResource[]|\Generator
   *   The list of files filtered by name and age.
   */
  protected function filter(\Traversable $files) {
    foreach ($files as $path => $resource) {
      if ($resource->isFile() && preg_match($this->sftpSettings['filename_pattern'], $resource->getFilename())) {
        $age_days = (time() - $resource->getModificationTime()) / 60 / 60 / 24;

        if ($age_days < $this->sftpSettings['file_max_age_days']) {
          yield $path => $resource;
        }
      }
    }
  }

  /**
   * Reduce the files array to the most recent file resource.
   *
   * @param \Traversable $files
   *   A list of files from SftpClientInterface::listFiles().
   *
   * @return \Drupal\sftp_client\SftpResource[]|\Generator
   *   The list of files filtered by name and age.
   */
  protected function getMostRecent(\Traversable $files) {
    $high_mark = 0;
    $newest_file = NULL;

    foreach ($files as $path => $resource) {
      if ($resource->getModificationTime() > $high_mark) {
        $high_mark = $resource->getModificationTime();
        $newest_file = $resource;
      }
    }

    return $newest_file;
  }

}
