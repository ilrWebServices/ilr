<?php

/**
 * @file
 * Post update functions for the Collection Blogs module.
 */

/**
 * Change attribute values for categories and tags in collections to include the
 * vocabulary id.
 */
function collection_blogs_post_update_fix_blog_vocabulary_collection_items(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $collection_item_storage = $entity_type_manager->getStorage('collection_item');

  foreach (['categories', 'tags'] as $vocabulary_type) {
    $vocabulary_collection_item_ids = $collection_item_storage->getQuery()
      ->condition('item__target_type', 'taxonomy_vocabulary')
      ->condition('attributes.key', 'blog_taxonomy_' . $vocabulary_type)
      ->execute();

    foreach ($collection_item_storage->loadMultiple($vocabulary_collection_item_ids) as $collection_item) {
      $collection_item->setAttribute('blog_taxonomy_' . $vocabulary_type, $collection_item->item->entity->id());
      $collection_item->canonical = TRUE;
      $collection_item->save();
    }
  }
}
