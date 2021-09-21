<?php

namespace Drupal\ilr_campaigns\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Updates course notification subscribers from webform data.
 *
 * @QueueWorker(
 *   id = "course_notification_subscriber",
 *   title = @Translation("Course Notification Subscriber"),
 *   cron = {"time" = 90}
 * )
 */
class CourseNotificationSubscriber extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    // @todo Inject?
    \Drupal::service('ilr_campaigns.course_notifications')->processSubscriber($data);
  }

}
