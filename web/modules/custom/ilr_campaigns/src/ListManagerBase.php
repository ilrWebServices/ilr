<?php

namespace Drupal\ilr_campaigns;

use CampaignMonitor\CampaignMonitorRestClient;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\webform\Entity\WebformSubmission;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use Psr\Log\LoggerInterface;

/**
 * The CM List Manager Base Class
 */
class ListManagerBase {

  /**
   * The Campaign Montitor REST Client.
   *
   * @var \CampaignMonitor\CampaignMonitorRestClient
   */
  protected $client;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The queue factory service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The ILR campaigns configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $settings;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The list ID setting. Typically overridden.
   */
  protected $listIdSettingName;

  /**
   * The custom field name. Typically overridden.
   */
  protected $customFieldName;

  /**
   * The webform id. Typically overridden.
   */
  protected $webformId;

  /**
   * Constructs a new list manager base object.
   *
   * @param \CampaignMonitor\CampaignMonitorRestClient $campaign_monitor_rest_client
   *   The Campaign Monitor rest client.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(CampaignMonitorRestClient $campaign_monitor_rest_client, EntityTypeManagerInterface $entity_type_manager, StateInterface $state, QueueFactory $queue_factory, ConfigFactoryInterface $config_factory, LoggerInterface $logger) {
    $this->client = $campaign_monitor_rest_client;
    $this->entityTypeManager = $entity_type_manager;
    $this->state = $state;
    $this->queueFactory = $queue_factory;
    $this->settings = $config_factory->get('ilr_campaigns.settings');
    $this->logger = $logger;
  }

  /**
   * Add options to the custom field.
   *
   * @return bool|null
   *   TRUE if field was successfully updated, FALSE if it was not. NULL if
   *   there was nothing to do.
   *
   * @todo Consider NOT removing/replacing renamed options, since that will remove users from segments.
   *
   * @todo Watch for removed options. If any, move users to the new option.
   */
  public function addCustomFieldOptions() {
    $list_id = $this->settings->get($this->listIdSettingName);

    if (empty($list_id)) {
      return;
    }

    $options = $this->getCustomFieldOptions();

    if (empty($options)) {
      return;
    }

    // Silently handle exceptions for all REST client API calls.
    try {
      // Save the custom field.
      $data = [
        'json' => [
          'KeepExistingOptions' => FALSE,
          'Options' => $options,
        ],
      ];

      $this->client->put("lists/$list_id/customfields/[{$this->customFieldName}]/options.json", $data);
    }
    catch (\Exception $e) {
      // @todo Log and continue. No WSOD for us!
      return FALSE;
    }

    return TRUE;
  }

  /**
   * todo
   *
   * @return array
   *   An array of the options.
   */
  protected function getCustomFieldOptions() {
    return [];
  }

  /**
   * Add new webform submissions to a queue.
   *
   * @see ilr_campaigns_cron()
   */
  public function queueSubscribers() {
    $this->logger->info('queueSubscribers');
    $subscriber_queue = $this->queueFactory->get($this->webformId . '_submission_processor');
    $last_queued_serial_id = $this->state->get('ilr_campaigns.subscriber_last_serial_' . $this->webformId, 0);
    $submission_storage = $this->entityTypeManager->getStorage('webform_submission');

    $submission_ids = $submission_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('webform_id', $this->webformId)
      ->condition('serial', $last_queued_serial_id, '>')
      ->exists('entity_id') // Ensure there is a source entity id.
      ->sort('serial')
      ->execute();

    foreach ($submission_storage->loadMultiple($submission_ids) as $submission) {
      $this->logger->info('Queuing submission @submission', [
        '@submission' => $submission->id(),
      ]);

      if ($subscriber_queue->createItem($submission)) {
        $last_queued_serial_id = $submission->serial->value;
      }
    }

    $this->state->set('ilr_campaigns.subscriber_last_serial_' . $this->webformId, $last_queued_serial_id);
  }

  /**
   * Add or update the subscriber based on the queue item.
   *
   * @throws \Exception
   *
   * @see CourseNotificationSubscriber::processItem()
   * @see BlogSubscriber::processItem()
   */
  public function processSubscriber($submission) {
    $list_id = $this->settings->get($this->listIdSettingName);

    if (empty($list_id)) {
      return;
    }

    $submission_data = $submission->getData();
    $email = $submission_data['email'];

    // Look up email and store any existing values from the customFieldName.
    try {
      $response = $this->client->get("subscribers/$list_id.json?email=$email");
      // Create an array of existing interests for merging.
      $subscriber_data = $response->getData();
      $subscriber_data['Resubscribe'] = TRUE;
      $subscriber_data['ConsentToTrack'] = 'Unchanged';
      $subscriber_data['Name'] = $submission_data['name'];
    }
    catch (ClientException $e) {
      $response = $e->getResponse();
      $response_data = $response->getData();

      // New subscriber, so add them to the list.
      if ($response->getStatusCode() === 400 && $response_data['Code'] === 203) {
        $subscriber_data = [
          'EmailAddress' => $email,
          'Name' => $submission_data['name'],
          'CustomFields' => [],
          'ConsentToTrack' => 'Yes',
        ];
      }
      // Stop processing the queue if the rate limit was reached.
      else if ($response->getStatusCode() === 429) {
        throw new SuspendQueueException('Rate limit reached.');
      }
      else {
        // Throw an error if the response code was 1.
        // Stop the queue if the API is down. How would we know?
        throw new \Exception('There was an issue with the API or email address.');
      }
    }
    catch (ConnectException $e) {
      throw new SuspendQueueException('API connection problem.');
    }

    if ($custom_field_value = $this->getCustomFieldValue($submission)) {
      // Add the custom field value.
      $subscriber_data['CustomFields'][] = [
        'Key' => $this->customFieldName,
        'Value' => $custom_field_value,
      ];
    }
    else {
      throw new \Exception('Could not determine custom field value for submission ' . $submission->id() . '.');
    }

    $post_data = [
      'json' => $subscriber_data,
    ];

    try {
      // Use the API to add or update the subscriber, which appears to actually
      // be an upsert.
      $response = $this->client->post("subscribers/$list_id.json", $post_data);
    }
    catch (ClientException $e) {
      $response = $e->getResponse();

      // Stop processing the queue if the rate limit was reached.
      if ($response->getStatusCode() === 429) {
        throw new SuspendQueueException('Rate limit reached.');
      }

      throw new \Exception($e->getMessage());
    }
    catch (ConnectException $e) {
      throw new SuspendQueueException('API connection problem.');
    }
  }

  /**
   * todo
   *
   * @param WebformSubmission $submission
   * @return string
   */
  protected function getCustomFieldValue(WebformSubmission $submission) {
    return $this->getOptionName($submission->getSourceEntity());
  }

  /**
   * Generate a user-friendly string that represents this entity.
   *
   * This will be output to the end-user in the preference center.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return string
   *   The entity label.
   */
  protected function getOptionName(ContentEntityInterface $entity) {
    return $entity->label();
  }
}
