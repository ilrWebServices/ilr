<?php

/**
 * @file
 * Post update functions for the ILR Campaigns module.
 */

/**
 * Update ilr_campaigns state key names.
 */
function ilr_campaigns_post_update_state_fix() {
  $last_field_update_course_notifications = \Drupal::state()->get('ilr_campaigns.course_notifier_subscriber_last_serial');
  \Drupal::state()->set('ilr_campaigns.subscriber_last_serial_course_notification', $last_field_update_course_notifications);
  \Drupal::state()->delete('ilr_campaigns.course_notifier_subscriber_last_serial');
  \Drupal::state()->delete('ilr_campaigns.custom_field_update');
}
