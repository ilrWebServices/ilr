<?php

namespace Drupal\ilr_campaigns;

use CampaignMonitor\CampaignMonitorRestClient;
use Drupal\Core\Entity\ContentEntityInterface;

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

  protected $entityTypeManager;

  /**
   * Constructs a new Course Notification Service object
   */
  public function __construct(CampaignMonitorRestClient $campaign_monitor_rest_client, $entity_type_manager) {
    $this->client = $campaign_monitor_rest_client;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Send mailing when a new class is created.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $class
   *   A new class node for which to trigger a notification email.
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
      $response = $client->get('clients.json');
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
                    'Clause' => $course_option_name,
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
          'HtmlUrl' => 'http://example.com/',
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

}
