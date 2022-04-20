<?php

namespace Drupal\ilr_campaigns;

use CampaignMonitor\CampaignMonitorRestClient;
use Drupal\collection\CollectionContentManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\Exception\ClientException;
use Psr\Log\LoggerInterface;

/**
 * The Blog Subscription helper service.
 */
class BlogSubscriptionHelper {

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
   * The collection content manager service.
   *
   * @var \Drupal\collection\CollectionContentManager
   */
  protected $collectionContentManager;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new Subscription Helper Service object.
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
   * @param \Drupal\collection\CollectionContentManager $collection_content_manager
   */
  public function __construct(CampaignMonitorRestClient $campaign_monitor_rest_client, EntityTypeManagerInterface $entity_type_manager, StateInterface $state, QueueFactory $queue_factory, ConfigFactoryInterface $config_factory, CollectionContentManager $collection_content_manager, LoggerInterface $logger) {
    $this->client = $campaign_monitor_rest_client;
    $this->entityTypeManager = $entity_type_manager;
    $this->state = $state;
    $this->queueFactory = $queue_factory;
    $this->settings = $config_factory->get('ilr_campaigns.settings');
    $this->collectionContentManager = $collection_content_manager;
    $this->logger = $logger;
  }

  /**
   * Add subscriber options to the 'SubscriberInterests' custom field.
   *
   * @todo Consider NOT removing/replacing renamed options, since that will remove users from segments.
   *
   * @todo Watch for removed options. If any, move users to the new option.
   */
  public function addSubscriberInterestOptions() {
    $list_id = $this->settings->get('blog_updates_list_id');

    if (empty($list_id)) {
      return;
    }

    // Only run every 24 hours.
    $last_field_update = $this->state->get('ilr_campaigns.subscriber_interests_field_update', 0);

    if ((REQUEST_TIME - $last_field_update) < 60 * 60 * 24) {
      return;
    }

    $submission_storage = $this->entityTypeManager->getStorage('webform_submission');

    // Load webform submissions.
    $ids = $submission_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('webform_id', 'blog_updates')
      ->execute();

    if (empty($ids)) {
      return;
    }

    $submissions = $submission_storage->loadMultiple($ids);
    $options = [];

    // Create options for each blog that has at least one subcriber.
    /** @var \Drupal\webform\WebformSubmissionInterface $submission */
    foreach ($submissions as $submission) {
      $blog_collection = $this->getSubmissionCollection($submission);

      if (!$blog_collection) {
        continue;
      }

      if (!in_array($blog_collection->label(), $options)) {
        $options[] = $blog_collection->label();
      }
    }

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

      $this->client->put("lists/$list_id/customfields/[SubscriberInterests]/options.json", $data);
    }
    catch (\Exception $e) {
      // @todo Log and continue. No WSOD for us!
    }

    $this->state->set('ilr_campaigns.subscriber_interests_field_update', REQUEST_TIME);
  }

  /**
   * Add new webform submissions to a queue.
   *
   * Note that this assumes the existence of a specific queue id.
   *
   * @see ilr_campaigns_cron()
   */
  public function queueSubscribers($webform_id = NULL) {
    if (!$webform_id) {
      throw new \Exception('queueSubscribers called without specifying the webform id.');
    }

    $subscriber_queue = $this->queueFactory->get($webform_id . '_submission_processor');

    if (!$subscriber_queue) {
      throw new \Exception('Queue not found for ' . $webform_id . '_submission_processor');
    }

    $last_queued_serial_id = $this->state->get("ilr_campaigns.{$webform_id}_subscriber_last_serial", 0);
    $submission_storage = $this->entityTypeManager->getStorage('webform_submission');

    $submission_ids = $submission_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('webform_id', $webform_id)
      ->condition('serial', $last_queued_serial_id, '>')
      ->sort('serial')
      ->execute();

    /** @var \Drupal\webform\WebformSubmissionInterface $submission */
    foreach ($submission_storage->loadMultiple($submission_ids) as $submission) {
      $source_entity = $submission->getSourceEntity();
      if (!$source_entity) {
        continue;
      }

      if ($subscriber_queue->createItem($submission)) {
        $last_queued_serial_id = $submission->serial->value;
      }
    }

    $this->state->set("ilr_campaigns.{$webform_id}_subscriber_last_serial", $last_queued_serial_id);
  }

  /**
   * Add or update the subscriber based on the queue item.
   *
   * @throws \Exception
   *
   * @see BlogSubscriber::processItem()
   */
  public function processSubscriber($submission) {
    $webform_id = $submission->getWebform()->id();
    $list_id = $this->settings->get($webform_id . '_list_id');

    if (empty($list_id)) {
      return;
    }

    $submission_data = $submission->getData();
    $email = $submission_data['email'];

    // Look up email and store any existing values from the 'Subscriber
    // interests' field.
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
      else {
        // Throw an error if the response code was 1.
        // Stop the queue if the API is down. How would we know?
        throw new \Exception('There was an issue with the API or email address.');
      }
    }

    $blog_collection = $this->getSubmissionCollection($submission);

    if (!$blog_collection) {
      throw new \Exception('Blog collection not found for this submission.');
    }

    // Add the subscriber interest to the custom field.
    $subscriber_data['CustomFields'][] = [
      'Key' => 'SubscriberInterests',
      'Value' => $blog_collection->label(),
    ];

    $post_data = [
      'json' => $subscriber_data,
    ];

    try {
      // Use the API to add or update the subscriber, which appears to actually
      // be an upsert.
      $response = $this->client->post("subscribers/$list_id.json", $post_data);
    }
    catch (ClientException $e) {
      // @todo throw a SuspendQueueException if the API is down, throttled, or something else?
      throw new \Exception($e->getMessage());
    }
  }

  /**
   * Determine which collection they subscribed to based on the source entity.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $submission
   *   The webform submission.
  */
  private function getSubmissionCollection($submission = NULL) {
    $source_entity = $submission->getSourceEntity();

    if (!$source_entity) {
      return NULL;
    }

    $source_entity_type = $source_entity->getEntityTypeId();

    switch ($source_entity_type) {
      case 'collection':
        $collection = $source_entity;
        break;
      case 'collection_item':
        $collection = $source_entity->collection->entity;
        break;
      case 'node':
        $collection = $this->collectionContentManager->getCanonicalCollectionForEntity($source_entity);
        break;
      default:
        return NULL;
    }

    return $collection;
  }

}
