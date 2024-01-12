<?php

/**
 * @file
 * Drupal hooks provided by the Drush package.
 */

/**
 * Executes a deploy function which is intended to update data, like entities,
 * after config is imported during a deployment.
 *
 * These are a higher level alternative to hook_update_n and hook_deploy_NAME
 * functions and have to be placed in a MODULE.deploy.php file.
 *
 * NAME can be arbitrary machine names. In contrast to hook_update_N() the
 * alphanumeric naming of functions in the file is the only thing which ensures
 * the execution order of those functions. If update order is mandatory,
 * you should add numerical prefix to NAME or make it completely numerical.
 *
 * Drupal also ensures to not execute the same hook_deploy_NAME() function
 * twice.
 *
 * @param array $sandbox
 *   Stores information for batch updates.
 *
 * @return string|null
 *   Optionally, hook_deploy_NAME() hooks may return a translated string
 *   that will be displayed to the user after the update has completed. If no
 *   message is returned, no message will be presented to the user.
 *
 * @throws \Exception
 *   In case of error, update hooks should throw an instance of
 *   \Exception with a meaningful message for the user.
 *
 * @ingroup update_api
 *
 * @see hook_update_N()
 * @see hook_post_update_NAME()
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
