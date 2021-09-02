<?php

/**
 * @file
 * ilr.views.inc file.
 */

/**
 * Implements hook_views_data().
 */
function ilr_views_data() {
  $data['node']['content_latest_version_link'] = [
    'title' => t('Content latest version link'),
    'field' => [
      'id' => 'content_latest_version_link',
    ],
    'help' => t('Requires the Content Moderation module'),
  ];

  return $data;
}
