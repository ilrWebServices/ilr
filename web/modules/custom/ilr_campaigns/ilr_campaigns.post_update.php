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

/**
 * Move email update submissions to new blog updates webform.
 */
function ilr_campaigns_post_update_move_email_update_subscribers(&$sandbox) {
  $submission_storage = \Drupal::entityTypeManager()->getStorage('webform_submission');

  $query = $submission_storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('webform_id', ['work_and_coronavirus_updates', '75th_updates', 'news_updates'], 'IN')
    ->exists('entity_id')
    ->sort('created');

  if (!isset($sandbox['total'])) {
    $total_query = clone $query;
    $sandbox['total'] = $total_query->count()->execute();
    $sandbox['current'] = 0;
  }

  // Get all legacy email update form submissions.
  $submission_ids = $query->range($sandbox['current'], 25)->execute();

  // Add them to the blog updates form as new submissions.
  /** @var \Drupal\webform\WebformSubmissionInterface $submission */
  foreach ($submission_storage->loadMultiple($submission_ids) as $submission) {
    /** @var \Drupal\webform\WebformSubmissionInterface $new_submission */
    $new_submission = $submission_storage->create([
      'webform_id' => 'blog_updates',
      'uri' => $submission->uri->value,
      'created' => $submission->created->value,
      'completed' => $submission->completed->value,
      'changed' => $submission->changed->value,
      'remote_addr' => $submission->remote_addr->value,
      'entity_type' => $submission->entity_type->value,
      'entity_id' => $submission->entity_id->value,
    ]);

    $new_submission->setData($submission->getData());
    $new_submission->save();
    $sandbox['current']++;
  }

  \Drupal::messenger()->addMessage($sandbox['current'] . ' submissions processed.');

  if ($sandbox['current'] >= $sandbox['total']) {
    $sandbox['#finished'] = 1;
  }
  else {
    $sandbox['#finished'] = ($sandbox['current'] / $sandbox['total']);
  }
}
