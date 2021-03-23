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
      $collection_item->weight = 10;
      $collection_item->save();
    }
  }
}

/**
 * Add all blog terms to their collections.
 */
function collection_blogs_post_update_collect_existing_terms(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $collection_item_storage = $entity_type_manager->getStorage('collection_item');
  $term_storage = $entity_type_manager->getStorage('taxonomy_term');

  // Find all auto-generated vocabularies.
  foreach (['categories', 'tags'] as $vocabulary_type) {
    $vocabulary_collection_item_ids = $collection_item_storage->getQuery()
      ->condition('item__target_type', 'taxonomy_vocabulary')
      ->condition('attributes.key', 'blog_taxonomy_' . $vocabulary_type)
      ->execute();

    foreach ($collection_item_storage->loadMultiple($vocabulary_collection_item_ids) as $collection_item) {
      $vocabulary = $collection_item->item->entity;
      $term_results = $term_storage->getQuery()
        ->condition('vid', $vocabulary->id())
        ->execute();

      // Set the default type
      $collection_type = $collection_item->collection->entity->type->entity;
      $allowed_collection_item_types = $collection_type->getAllowedCollectionItemTypes('taxonomy_term', $vocabulary->id());
      $type = ($allowed_collection_item_types)
        ? reset($allowed_collection_item_types)
        : 'default';

      foreach ($term_storage->loadMultiple($term_results) as $term) {
        $collection_item_term = $collection_item_storage->create([
          'type' => $type,
          'collection' => $collection_item->collection->entity,
          'canonical' => TRUE,
          'changed' => $term->getChangedTime(),
        ]);

        $collection_item_term->item = $term;
        $collection_item_term->save();
      }
    }
  }
}

