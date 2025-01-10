<?php

/**
 * @file
 * Provide views data for ilr_neutrals.
 */

declare(strict_types=1);

/**
 * Implements hook_views_data().
 */
function ilr_neutrals_views_data(): array {
  $data = [];

  $data['ilr_neutrals_remote_data']['table']['group'] = 'ilr_neutrals_remote_data';
  $data['ilr_neutrals_remote_data']['table']['base'] = [
    'title' => 'Remote Neutrals data',
    'query_id' => 'views_remote_data_query',
  ];

  return $data;
}
