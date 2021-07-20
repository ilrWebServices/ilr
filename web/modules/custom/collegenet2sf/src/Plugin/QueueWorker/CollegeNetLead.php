<?php

namespace Drupal\collegenet2sf\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\salesforce\Rest\RestClientInterface;
use Drupal\salesforce\Rest\RestException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\salesforce\SelectQuery;
use Drupal\Core\Queue\SuspendQueueException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes items for CollegeNET prospects to Salesforce Leads.
 *
 * @QueueWorker(
 *   id = "collegenet_lead_queue",
 *   title = @Translation("CollegeNET to Lead queue worker"),
 *   cron = {"time" = 180}
 * )
 */
class CollegeNetLead extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use LoggerChannelTrait;

  /**
   * Salesforce client.
   *
   * @var \Drupal\salesforce\Rest\RestClientInterface
   */
  protected $sfapi;

  /**
   * The config Factory to load the configuration.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory to load the configuration including overrides from.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RestClientInterface $sfapi, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->sfapi = $sfapi;
    $this->configFactory = $config_factory;
    $this->logger = $this->getLogger('collegenet lead');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('salesforce.client'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * $data should include:
   *
   * [
   *   "XACT_ID" => "12345678"
   *   "NAME_FIRST" => "Barney"
   *   "NAME_LAST" => "Rubble"
   *   "EMAIL" => "brubble@gmail.com"
   *   "CORNELLG-CELL_PHONE" => "555-1212"
   *   "CREATE_DATE" => "2020-09-11T04:34:36"
   *   "SOURCE" => "Pre-Assessment - 2020_09_11"
   *   "PRE-ASSESSMENT_SOURCE" => "Industry Connections"
   *   "CORNELLG-APP_TERM_DISPLAY" => "Fall 2021"
   *   "APPLICANT_STATUS" => "Reject"
   *   "MJR_PROGRAM_NAME" => "Industrial and Labor Relations, M.I.L.R."
   *   "OFFER_PROGRAM" => "Industrial and Labor Relations, M.I.L.R."
   *   "APPLICATION_STATUS" => ""
   *   "CORNELLG-GENDER" => "M"
   *   "CORNELLG-ETHNIC" => ""
   *   "CORNELLG-HISPANIC_YN" => ""
   *   "DECISION_RESPONSE" => ""
   * ]
   */
  public function processItem($data) {
    // Get the CollegeNET to Lead field mapping.
    $mapping = $this->configFactory->get('collegenet2sf.settings')->get('mapping');

    // @todo Remove when dev server has these fields properly configured.
    unset($mapping['OFFER_PROGRAM']);
    // unset($mapping['MJR_PROGRAM_NAME']);
    unset($mapping['DECISION_RESPONSE']);

    // Check for MILR (record type) Lead with same email and missing CollegeNET
    // XACT_ID.
    try {
      $query = new SelectQuery('Lead');
      $query->fields = ['Id'];
      $query->addCondition('Email', "'{$data['EMAIL']}'");
      $query->addCondition('RecordType.Name', "'MILR'");
      $query->addCondition('XACT_ID__c', "''");
      $query->order['LastModifiedDate'] = 'DESC';

      $response = $this->sfapi->apiCall('query?q=' . (string) $query, [], 'GET', TRUE);

      if (!empty($response->data['totalSize'])) {
        $existing_lead = reset($response->data['records']);
      }
    }
    catch (RestException $e) {
      $this->logger->error('Error processing CollegeNET prospect record @xact_id. Message: @message', [
        '@xact_id' => $data['XACT_ID'],
        '@message' => $e->getMessage(),
      ]);

      // If this was a connection error, throw a SuspendQueueException exception
      // so that will be suspended.
      // @todo See if this is logged. If not, log it ourselves.
      if ($e->getResponse() === NULL) {
        throw new SuspendQueueException($e->getMessage());
      }
      else {
        // This will be logged. I think.
        throw new \Exception($e->getMessage());
      }
    }

    // If a Lead for this email exists and has no XACT_ID, add the XACT_ID and
    // save it.
    if (!empty($existing_lead)) {
      try {
        $response = $this->sfapi->apiCall("sobjects/Lead/{$existing_lead['Id']}", ['XACT_ID__c' => $data['XACT_ID']], 'PATCH');
      }
      catch (RestException $e) {
        throw new \Exception($e->getMessage());
      }
    }

    // Initialize data for this SF object. Constants can be added here.
    $lead_data = [
      'Company' => 'NONE PROVIDED',
      'RecordType' => [
        'Name' => 'MILR',
      ],
    ];

    foreach ($data as $collegenet_key => $value) {
      if ($value && array_key_exists($collegenet_key, $mapping)) {
        $lead_data[$mapping[$collegenet_key]] = $value;
      }
    }

    // Upsert the Lead records, using the XACT_ID as the upsert id.
    try {
      $sfid = $this->sfapi->objectUpsert('Lead', 'XACT_ID__c', $data['XACT_ID'], $lead_data);
    }
    catch (RestException $e) {
      throw new \Exception($e->getMessage());
    }
  }

}
