<?php

/**
 * @file
 * Drupal hooks provided by the Drush package.
 */

/**
 * Put the cahrs member orgs vocabulary in the cahrs collection.
 */
function ilr_deploy_collect_cahrs_member_org_vocab(array &$sandbox): string {
  $entity_manager = \Drupal::entityTypeManager();

  if ($vocabulary = $entity_manager->getStorage('taxonomy_vocabulary')->load('cahrs_member_organizations')) {
    $collection_item = $entity_manager->getStorage('collection_item')->create([
      'type' => 'default',
      'collection' => 53,
      'item' => $vocabulary,
      'canonical' => TRUE,
      'weight' => 10,
    ]);
    $collection_item->save();
  }

  return t('Collection item %id saved', ['%id' => $collection_item->id()]);
}
