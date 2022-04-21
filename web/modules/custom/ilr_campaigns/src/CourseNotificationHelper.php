<?php

namespace Drupal\ilr_campaigns;

use CampaignMonitor\CampaignMonitorRestClient;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use GuzzleHttp\Exception\ClientException;
use Psr\Log\LoggerInterface;

/**
 * The Course Notification helper service.
 */
class CourseNotificationHelper {

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
   * Constructs a new Course Notification Service object.
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
   * Send mailing when a new class is created.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $class
   *   A new class node for which to trigger a notification email.
   *
   * @todo Deal with overflow classes if necessary.
   */
  public function createClassNotification(ContentEntityInterface $class) {
    $course = $class->field_course->entity;
    $course_option_name = $this->getCourseOptionName($course);

    // Silently handle exceptions for all REST client API calls.
    try {
      $client_id = $this->settings->get('course_notification_client_id');
      $list_id = $this->settings->get('course_notification_list_id');

      if (empty($client_id) || empty($list_id)) {
        return;
      }

      // Look up segments for the 'Course Notifications' list for a segment that
      // has the related course number in the name.
      $response = $this->client->get("lists/$list_id/segments.json");
      $segments = $response->getData();
      $segment_array_key = array_search($course_option_name, array_column($segments, 'Title'));

      if ($segment_array_key !== FALSE) {
        $segment_id = $segments[$segment_array_key]['SegmentID'];
      }
      // If there is no segment, create one for this course.
      else {
        $data = [
          'json' => [
            'Title' => $course_option_name,
            'RuleGroups' => [
              [
                'Rules' => [
                  [
                    'RuleType' => '[CourseNotifications]',
                    'Clause' => 'EQUALS ' . $course_option_name,
                  ],
                ],
              ],
            ],
          ],
        ];

        $response = $this->client->post("segments/$list_id.json", $data);
        $segment_id = $response->getData();
      }

      // Create a new campaign for this Course and Class, using the class html
      // page.
      $campaign_data = [
        'json' => [
          'Name' => 'Course notification for ' . $course_option_name . ' [Class:' . $class->id() . ']',
          'Subject' => 'New date announced for ' . $course_option_name,
          'FromName' => 'ILR Customer Service',
          'FromEmail' => 'ilrcustomerservice@cornell.edu',
          'ReplyTo' => 'ilrcustomerservice@cornell.edu',
          'HtmlUrl' => $class->toUrl('ilr_campaigns_email', ['absolute' => TRUE])->toString(),
          'SegmentIDs' => [$segment_id],
        ],
      ];

      $response = $this->client->post("campaigns/$client_id.json", $campaign_data);
      $campaign_id = $response->getData();

      // Set to send at 9am next day.
      $send_date = new \DateTime('tomorrow');
      $send_date->setTime(9, 01);

      $data = [
        'json' => [
          'ConfirmationEmail' => 'ilrweb@cornell.edu',
          'SendDate' => $send_date->format('Y-m-d H:i'),
        ],
      ];

      $response = $this->client->post("campaigns/$campaign_id/send.json", $data);

      // Get the details of the scheduled campaign.
      $response = $this->client->get("campaigns/$campaign_id/summary.json");
      $campaign_summary = $response->getData();
      $campaign_data['summary'] = [
        'preview' => $campaign_summary['WebVersionURL'],
        'recipients' => $campaign_summary['Recipients'],
      ];

      // Log the details of the new campaign.
      $this->logger->info('Campaign @campaign created and scheduled: @data', [
        '@campaign' => $campaign_id,
        '@data' => print_r($campaign_data, TRUE),
      ]);
    }
    catch (\Exception $e) {
      // @todo Log and continue. No WSOD for us!
      throw new \Exception($e->getMessage());
    }
  }

  /**
   * Add course options to the 'Course Notifications' custom field.
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
    $list_id = $this->settings->get('course_notification_list_id');

    if (empty($list_id)) {
      return;
    }

    $node_storage = $this->entityTypeManager->getStorage('node');

    // Load courses.
    $ids = $node_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'course')
      ->execute();

    if (empty($ids)) {
      return;
    }

    $courses = $node_storage->loadMultiple($ids);
    $options = [];

    // Create options for each course and number.
    foreach ($courses as $course) {
      $options[] = $this->getCourseOptionName($course);
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

      $this->client->put("lists/$list_id/customfields/[CourseNotifications]/options.json", $data);
    }
    catch (\Exception $e) {
      // @todo Log and continue. No WSOD for us!
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Add new course notification webform submissions to a queue.
   *
   * @see ilr_campaigns_cron()
   */
  public function queueSubscribers() {
    $subscriber_queue = $this->queueFactory->get('course_notification_submission_processor');
    $last_queued_serial_id = $this->state->get('ilr_campaigns.course_notifier_subscriber_last_serial', 0);
    $submission_storage = $this->entityTypeManager->getStorage('webform_submission');

    $submission_ids = $submission_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('webform_id', 'course_notification')
      ->condition('serial', $last_queued_serial_id, '>')
      ->sort('serial')
      ->execute();

    foreach ($submission_storage->loadMultiple($submission_ids) as $submission) {
      $source_entity = $submission->getSourceEntity();
      if (!$source_entity || $source_entity->getEntityTypeId() !== 'node' || $source_entity->bundle() !== 'course') {
        continue;
      }

      if ($subscriber_queue->createItem($submission)) {
        $last_queued_serial_id = $submission->serial->value;
      }
    }

    $this->state->set('ilr_campaigns.course_notifier_subscriber_last_serial', $last_queued_serial_id);
  }

  /**
   * Add or update the subscriber based on the queue item.
   *
   * @throws \Exception
   *
   * @see CourseNotificationSubscriber::processItem()
   */
  public function processSubscriber($submission) {
    $list_id = $this->settings->get('course_notification_list_id');

    if (empty($list_id)) {
      return;
    }

    $submission_data = $submission->getData();
    $email = $submission_data['email'];

    // Look up email and store any existing values from the 'Course
    // Notifications' field.
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

    $requested_course = $submission->getSourceEntity();

    if (!$requested_course) {
      throw new \Exception('Source entity (course) not found for this submission.');
    }

    // Add the requested course to the custom field.
    $subscriber_data['CustomFields'][] = [
      'Key' => 'CourseNotifications',
      'Value' => $this->getCourseOptionName($requested_course),
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
   * Generate a user-friendly string that represents this course.
   *
   * This will be output to the end-user in the preference center.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $course
   *   The course node.
   *
   * @return string
   *   The course name with its number in parens.
   */
  private function getCourseOptionName(ContentEntityInterface $course) {
    return strtr('!course_name [!course_number]', [
      '!course_name' => $course->label(),
      '!course_number' => $course->field_course_number->value,
    ]);
  }

}
