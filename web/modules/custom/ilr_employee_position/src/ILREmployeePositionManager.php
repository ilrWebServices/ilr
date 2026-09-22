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

  public function getEmployeePositions(int $pid, $limit_published = FALSE): array {
    $position_storage = $this->entityTypeManager->getStorage('ilr_employee_position');
    $positions = [];

    $position_query = $position_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('persona', $pid)
      ->sort('primary', 'DESC');

    if ($limit_published) {
      $position_query->condition('status', 1);
    }

    $position_ids = $position_query->execute();

    if (!$position_ids) {
      return [];
    }

    foreach ($position_storage->loadMultiple($position_ids) as $position) {
      $positions[] = $position;
    }

    return $positions;
  }
}
