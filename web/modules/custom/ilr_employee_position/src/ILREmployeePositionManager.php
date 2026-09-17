<?php

namespace Drupal\ilr_employee_position;

use Drupal\Core\Entity\EntityTypeManager;

/**
 * The collection content manager service.
 */
class ILREmployeePositionManager {

  /**
   * CollectionContentManager constructor.
   */
  public function __construct(
    protected EntityTypeManager $entityTypeManager) {
  }

  public function getEmployeePositions(int $pid): array {
    $persona_storage = $this->entityTypeManager->getStorage('ilr_employee_position');
    $positions = [];

    $position_ids = $persona_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('persona', $pid)
      ->condition('status', 1)
      ->sort('primary', 'DESC')
      ->execute();

    if (!$position_ids) {
      return [];
    }

    foreach ($persona_storage->loadMultiple($position_ids) as $position) {
      $positions[] = $position;
    }

    return $positions;
  }

}
