<?php

namespace Drupal\ilr_campaigns;

use CampaignMonitor\CampaignMonitorRestClient;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Entity\ContentEntityInterface;
use GuzzleHttp\Exception\ClientException;

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
   * Constructs a new Course Notification Service object
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory
   */
  public function __construct(CampaignMonitorRestClient $campaign_monitor_rest_client, EntityTypeManagerInterface $entity_type_manager, StateInterface $state, QueueFactory $queue_factory) {
    $this->client = $campaign_monitor_rest_client;
    $this->entityTypeManager = $entity_type_manager;
    $this->state = $state;
    $this->queueFactory = $queue_factory;
  }

  /**
   * Send mailing when a new class is created.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $class
   *   A new class node for which to trigger a notification email.
   *
   * @todo Deal with overflow classes if necessary.
   *
   * @return void
   */
  public function processNewClass(ContentEntityInterface $class) {
    $course = $class->field_course->entity;
    // Bail if this Class and the related Course are not published.
    if (!$class->isPublished() || !$course->isPublished()) {
      return;
    }

    $course_option_name = strtr('!course_name (!course_number)', [
      '!course_name' => $course->label(),
      '!course_number' => $course->field_course_number->value,
    ]);

    $segment_title = $course_option_name . ' Segment';

    // Silently handle exceptions for all REST client API calls.
    try {
      // Get the first client_id for this API key. If there are more clients, I
      // guess we'll need to do something else here.
      $response = $this->client->get('clients.json');
      $data = $response->getData();
      $client_id = $data[0]['ClientID'] ?? FALSE;

      if (empty($client_id)) {
        return;
      }

      // Look for a 'Course Notifications' list. Bail if there isn't one.
      $response = $this->client->get("clients/$client_id/lists.json");
      $data = $response->getData();
      $list_array_key = array_search('Course Notifications', array_column($data, 'Name'));

      if ($list_array_key !== FALSE) {
        $list_id = $data[$list_array_key]['ListID'];
      }
      else {
        // @todo Log missing list.
        return;
      }

      // Look up segments for the 'Course Notifications' list for a segment that
      // has the related course number in the name.
      $response = $this->client->get("lists/$list_id/segments.json");
      $segments = $response->getData();
      $segment_array_key = array_search($segment_title, array_column($segments, 'Title'));

      if ($segment_array_key !== FALSE) {
        $segment_id = $segments[$segment_array_key]['SegmentID'];
      }
      // If there is no segment, create one for this course called 'COURSE_NAME
      // (COURSE_NUMBER) Segment', e.g. Advanced Collective Bargaining (LS252)
      // Segment.
      //   - Single rule is 'Course notifications' matches exactly COURSE_NAME
      //     (COURSE_NUMBER)
      else {
        $data = [
          'json' => [
            'Title' => $segment_title,
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
      $data = [
        'json' => [
          'Name' => 'Course notification for ' . $course->field_course_number->value,
          'Subject' => 'New date announced for ' . $course->label(),
          'FromName' => 'ILR Customer Service',
          'FromEmail' => 'ilrcustomerservice@cornell.edu',
          'ReplyTo' => 'ilrcustomerservice@cornell.edu',
          'HtmlUrl' => $class->toUrl('ilr_campaigns_email', ['absolute' => TRUE])->toString(),
          'SegmentIDs' => [$segment_id],
        ],
      ];

      $response = $this->client->post("campaigns/$client_id.json", $data);
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

      // Log the details of the new campaign with much detail.
    }
    catch (\Exception $e) {
      // @todo Log and continue. No WSOD for us!
      throw new \Exception($e->getMessage());
    }
  }

  /**
   * Add course options to the 'Course Notifications' custom field.
   *
   * @todo Consider NOT removing/replacing renamed options, since that will remove users from segments.
   *
   * @todo Watch for removed options. If any, move users to the new option.
   *
   *  @return void
   */
  public function addCustomFieldOptions() {
    // Load all Courses.
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
      $options[] = strtr('!course_name (!course_number)', [
        '!course_name' => $course->title->value,
        '!course_number' => $course->field_course_number->value,
      ]);
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

      $list_id = 'ed2b66a59957f2c0942c0a70511e9c82';

      $response = $this->client->put("lists/$list_id/customfields/[CourseNotifications]/options.json", $data);
    }
    catch (\Exception $e) {
      // @todo Log and continue. No WSOD for us!
    }
  }

  /**
   * Undocumented function
   *
   * @return void
   *
   * @see ilr_campaigns_cron().
   */
  public function queueSubscribers() {
    // Inject the queueworker.
    $subscriber_queue = $this->queueFactory->get('course_notification_subscriber');

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
   * @throws Exception||Drupal\Core\Queue\RequeueException||Drupal\Core\Queue\SuspendQueueException
   *
   * @see CourseNotificationSubscriber::processItem().
   */
  public function processSubscriber($submission) {
    $list_id = 'ed2b66a59957f2c0942c0a70511e9c82';
    $submission_data = $submission->getData();
    $email = $submission_data['email'];

    // Look up email and store any existing values from the 'Course Notifications' field.
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
        // throw an error if the response code was 1.
        // Stop the queue if the API is down. How would we know?
        throw new \Exception('There was an issue with the API or email address.');
      }
    }

    $requested_course = $submission->getSourceEntity();

    // Add the requested course to the custom field.
    $course_option_name = strtr('!course_name (!course_number)', [
      '!course_name' => $requested_course->label(),
      '!course_number' => $requested_course->field_course_number->value,
    ]);

    $subscriber_data['CustomFields'][] = [
      'Key' => 'CourseNotifications',
      'Value' => $course_option_name,
    ];

    $post_data = [
      'json' => $subscriber_data,
    ];

    try {
      // Use the API to add or update the subscriber, which appears to actually be an upsert.
      $response = $this->client->post("subscribers/$list_id.json", $post_data);
    }
    catch (ClientException $e) {
      // @todo throw a SuspendQueueException if the API is down, throttled, or something else?
      throw new \Exception($e->getMessage());
    }
  }

}
