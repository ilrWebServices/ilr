<?php

namespace Drupal\ilr_campaigns;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\webform\Entity\WebformSubmission;

/**
 * The Course Notification helper service.
 */
class CourseNotificationHelper extends ListManagerBase {

  /**
   * The list ID setting.
   */
  protected $listIdSettingName = 'course_notification_list_id';

  /**
   * The custom field name.
   */
  protected $customFieldName = 'CourseNotifications';

  /**
   * The webform id.
   */
  protected $webformId = 'course_notification';

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
    $course_option_name = $this->getOptionName($course);

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
   * todo
   *
   * @return array
   *   An array of the options.
   */
  protected function getCustomFieldOptions() {
    $node_storage = $this->entityTypeManager->getStorage('node');
    $options = [];

    // Load courses.
    $ids = $node_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'course')
      ->execute();

    // Create options for each course and number.
    foreach ($node_storage->loadMultiple($ids) as $course) {
      $options[] = $this->getOptionName($course);
    }

    return $options;
  }

  /**
   * @inheritDoc
   */
  protected function getCustomFieldValue(WebformSubmission $submission) {
    $requested_course = $submission->getSourceEntity();
    return $this->getOptionName($requested_course);
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
  protected function getOptionName(ContentEntityInterface $course) {
    return strtr('!course_name [!course_number]', [
      '!course_name' => $course->label(),
      '!course_number' => $course->field_course_number->value,
    ]);
  }

}
