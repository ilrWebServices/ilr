<?php

/**
 * @file
 * Post update functions for the Collection Blogs module.
 */

/**
 * Implements hook_removed_post_updates().
 */
function collection_blogs_removed_post_updates() {
  return [
    'collection_blogs_post_update_fix_blog_vocabulary_collection_items' => '9.4.0',
    'collection_blogs_post_update_collect_existing_terms' => '9.4.0',
  ];
}
