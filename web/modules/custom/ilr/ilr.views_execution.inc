<?php

/**
 * @file
 * Provide views runtime hooks for view_unpublished.module.
 *
 * @see https://git.drupalcode.org/project/view_unpublished/-/blob/8.x-1.x/view_unpublished.views_execution.inc
 */

declare(strict_types = 1);

use Drupal\node\Entity\NodeType;
use Drupal\views\ViewExecutable;

/**
 * Implements hook_views_query_substitutions().
 */
function ilr_views_query_substitutions(ViewExecutable $view): array {
  $account = \Drupal::currentUser();
  $substitutions = [];
  foreach (NodeType::loadMultiple() as $type) {
    $type_id = $type->id();
    $substitutions["***VIEWUNPUBLISHED_TYPE_$type_id***"] = (int) $account->hasPermission('view any ' . $type_id . ' unpublished content');
  }

  return $substitutions;
}
