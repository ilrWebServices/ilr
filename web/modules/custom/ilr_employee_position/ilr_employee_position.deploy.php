<?php

use Drupal\ilr_employee_position\ILREmployeePositionInterface;

/**
 * Set primary ilr_employee_position weights to -1.
 */
function ilr_employee_position_deploy_add_weight_to_positions() {
  $storage = \Drupal::entityTypeManager()->getStorage('ilr_employee_position');

  $ids = \Drupal::entityQuery('ilr_employee_position')
    ->accessCheck(FALSE)
    ->condition('primary', 1)
    ->execute();

  /** @var ILREmployeePositionInterface $ilr_employee_position */
  foreach ($storage->loadMultiple($ids) as $ilr_employee_position) {
    $ilr_employee_position->set('weight', -1);
    $ilr_employee_position->save();
  }
}
