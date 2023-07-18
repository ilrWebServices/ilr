<?php

/**
 * @file
 * Provide views data for view_unpublished.module.
 *
 * @see https://git.drupalcode.org/project/view_unpublished/-/raw/8.x-1.x/view_unpublished.views.inc
 */

declare(strict_types = 1);

use Drupal\ilr\Plugin\views\filter\NodeStatus;

/**
 * Implements hook_views_plugins_filter_alter().
 */
function ilr_views_plugins_filter_alter(array &$plugins): void {
  // Change the 'status' handler class.
  $plugins['node_status']['class'] = NodeStatus::class;
}
