<?php

namespace Drupal\ilr_migrate\Plugin\migrate\source;

use Drupal\node\Plugin\migrate\source\d7\Node as d7_node;

/**
 * Drupal 7 node source from database.
 *
 * @MigrateSource(
 *   id = "ilr_d7_node",
 *   source_module = "node"
 * )
 */
class IlrD7Node extends d7_node {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();

    if (isset($this->configuration['node_status'])) {
      $query->condition('n.status', $this->configuration['node_status']);
    }

    return $query;
  }

}
