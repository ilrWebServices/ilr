<?php

namespace Drupal\collegenet2sf\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\salesforce\Rest\RestClientInterface;
use Drupal\salesforce\Rest\RestException;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\Queue\RequeueException;
use League\Csv\Reader;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Reports errors on Bulk API 2.0 job results.
 *
 * @QueueWorker(
 *   id = "collegenet_bulk_job",
 *   title = @Translation("Salesforce Bulk API 2.0 job reporting queue worker"),
 *   cron = {"time" = 180}
 * )
 */
class CollegeNetBulkJob extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use LoggerChannelTrait;

  /**
   * Salesforce client.
   *
   * @var \Drupal\salesforce\Rest\RestClientInterface
   */
  protected $sfapi;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a CollegeNET to Lead queue object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\salesforce\Rest\RestClientInterface $sfapi
   *   Salesforce service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RestClientInterface $sfapi) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->sfapi = $sfapi;
    $this->logger = $this->getLogger('collegenet to salesforce');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('salesforce.client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    try {
      $job_info_response = $this->sfapi->apiCall('jobs/ingest/' . $data, [], 'GET', TRUE);
    }
    catch (RestException $e) {
      $this->logger->error('Error loading job @job_id: @message', [
        '@job_id' => $data,
        '@message' => $e->getMessage(),
      ]);

      // If this was a connection error, throw a SuspendQueueException exception
      // so that further queue processing will be suspended.
      // @todo See if this is logged. If not, log it ourselves.
      if ($e->getResponse() === NULL) {
        throw new SuspendQueueException($e->getMessage());
      }
      else {
        // This will be logged.
        throw new \Exception($e->getMessage());
      }
    }

    // Try again later if this job hasn't completed, yet.
    if ($job_info_response->data['state'] !== 'JobComplete') {
      throw new RequeueException();
    }

    // Log informational message.
    $this->logger->info('Bulk API 2.0 job @job_id was completed at @datetime. @processed record(s) processed, and @failures failed.', [
      '@job_id' => $data,
      '@datetime' => $job_info_response->data['systemModstamp'],
      '@processed' => $job_info_response->data['numberRecordsProcessed'],
      '@failures' => $job_info_response->data['numberRecordsFailed'],
    ]);

    // Get any failures and log them.
    try {
      $job_failure_response = $this->sfapi->apiCall("jobs/ingest/$data/failedResults", [], 'GET', TRUE);
    }
    catch (RestException $e) {
      throw new \Exception($e->getMessage());
    }

    // Parse the response CSV data. If this throws an error, the item will be
    // re-queued.
    $reader = Reader::createFromString($job_failure_response->data);
    $reader->setHeaderOffset(0);

    foreach ($reader->getRecords() as $record) {
      $external_id_field = $job_info_response->data['externalIdFieldName'];

      $this->logger->error('Error on job @job_id for @external_id: @message', [
        '@job_id' => $data,
        '@external_id' => $record[$external_id_field],
        '@message' => $record['sf__Error'],
      ]);
    }
  }

}
