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

    if (isset($this->configuration['node_terms'])) {
      $term_field = $this->configuration['node_terms'];
      $query->leftJoin("field_data_{$term_field}", 'fd', 'fd.entity_id = n.nid');
      $query->leftJoin('taxonomy_term_data', 'td', "td.tid = fd.{$term_field}_tid");
      $query->addExpression('GROUP_CONCAT(td.name)', 'node_terms');

      // Only return one row per node.
      $query->groupBy('n.nid');

      // Deal with Drupal's groupBy mysql settings.
      // @see https://www.drupal.org/project/drupal/issues/567148.
      $query->groupBy('n.type');
      $query->groupBy('n.language');
      $query->groupBy('n.status');
      $query->groupBy('n.created');
      $query->groupBy('n.changed');
      $query->groupBy('n.comment');
      $query->groupBy('n.promote');
      $query->groupBy('n.sticky');
      $query->groupBy('n.tnid');
      $query->groupBy('n.translate');
      $query->groupBy('nr.vid');
      $query->groupBy('nr.title');
      $query->groupBy('nr.log');
      $query->groupBy('nr.timestamp');
      $query->groupBy('n.uid');
      $query->groupBy('nr.uid');
    }

    return $query;
  }

}
