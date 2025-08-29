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

/**
 * Map existing CAROW news subscriptions submissions to Touchpoints.
 */
function ilr_deploy_map_carow_news_to_touchpoints(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');

  $submissions = $entity_type_manager->getStorage('webform_submission')->loadByProperties([
    'webform_id' => 'carow_newsletter_signup',
  ]);

  /** @var \Drupal\Webform\WebformSubmissionInterface $submission */
  foreach ($submissions as $submission) {
    /** @var \Drupal\salesforce_mapping\Entity\MappedObjectInterface $mapped_object */
    $mapped_object = $entity_type_manager->getStorage('salesforce_mapped_object')->create([
      'salesforce_mapping' => 'info_req_tp_carow',
    ]);
    $mapped_object->setDrupalEntity($submission);

    try {
      $mapped_object->push();
    }
    catch (\Exception $e) {
      // Fail silently.
    }
  }
}

/**
 * Add missing publications paragraph headings.
 */
function ilr_deploy_add_missing_publication_paragraph_headings(&$sandbox) {
  $publications_paragraphs = \Drupal::entityTypeManager()->getStorage('paragraph')->loadByProperties(['type' => 'publications']);

  /** @var \Drupal\paragraphs\ParagraphInterface $publications_paragraph */
  foreach ($publications_paragraphs as $publications_paragraph) {
    if ($publications_paragraph->hasField('field_heading') && $publications_paragraph->get('field_heading')->isEmpty()) {
      $publications_paragraph->set('field_heading', 'Publications');
      $publications_paragraph->save();
    }
  }
}

/**
 * Fix langcode for persona redirects.
 */
function ilr_deploy_fix_persona_redirect_langcodes(&$sandbox) {
  $redirect_query = \Drupal::entityTypeManager()->getStorage('redirect')->getQuery()
    ->accessCheck(FALSE)
    ->condition('redirect_redirect__uri', 'internal:/persona', 'STARTS_WITH')
    ->condition('language', 'en');

  $redirect_ids = $redirect_query->execute();
  $redirects = \Drupal::entityTypeManager()->getStorage('redirect')->loadMultiple($redirect_ids);

  foreach ($redirects as $redirect) {
    $redirect->set('language', 'und');
    $redirect->save();
  }
}

/**
 * Consolidate High Road posts.
 */
function ilr_deploy_highroad_post_consolidatereror_final_v2(&$sandbox) {
  // Move posts from Buffalo Colab collection categorized as 'High Road' to High
  // Road collection with the 'Buffalo' category. Move posts from High Road New
  // York State collection to High Road collection with the 'New York City'
  // category. Buffalo colab collection cid is 35. High Road New York State blog
  // collection cid is 55.
  $collection_item_storage = \Drupal::entityTypeManager()->getStorage('collection_item');
  $item_query = $collection_item_storage->getQuery();
  $item_query->accessCheck(FALSE);
  $item_query->condition('collection', [35, 55], 'IN');
  $item_query->condition('type', 'blog');

  $item_ids = $item_query->execute();
  $items = $collection_item_storage->loadMultiple($item_ids);

  foreach ($items as $item) {
    // tid 136 is 'High Road' in the Buffalo Colab collection.
    if ((!$item->field_blog_categories->isEmpty() && $item->field_blog_categories->entity->id() === '136') || $item->collection->entity->id() === '55') {
      $item->set('collection', 70);
      // tid 771 is 'Buffalo' in the High Road collection. tid 773 is 'New York
      // City' in the High Road collection.
      $item->set('field_blog_categories', (!$item->field_blog_categories->isEmpty() && $item->field_blog_categories->entity->id() === '136') ? 771 : 773);
      $item->save();
    }
  }
}

/**
 * Set all post representative images as featured media.
 */
function ilr_deploy_post_featured_media_create(&$sandbox) {
  $node_storage = \Drupal::entityTypeManager()->getStorage('node');
  $post_query = $node_storage->getQuery();
  $post_query->accessCheck(FALSE);
  $post_query->condition('type', 'post');
  $post_query->condition('field_representative_image', NULL, 'IS NOT NULL');

  $post_ids = $post_query->execute();
  $posts = $node_storage->loadMultiple($post_ids);

  foreach ($posts as $post) {
    $post->field_featured_media = $post->field_representative_image;
    $post->save();
  }
}

/**
 * Transform Salesforce mapped objects for grad programs into keystore entries.
 */
function ilr_deploy_transform_grad_lead_mapped_objects(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $mapped_object_storage = $entity_type_manager->getStorage('salesforce_mapped_object');
  $sfDataStore = \Drupal::service('keyvalue')->get('ilr_salesforce.touchpoint.sfid');

  $mapped_objects = $mapped_object_storage->loadByProperties([
    'salesforce_mapping' => [
      'milr_webform_submission_lead',
      'emhrm_webform_submission_lead',
    ],
  ]);

  foreach ($mapped_objects as $mapped_object) {
    $webform_submission = $mapped_object->getMappedEntity();
    $sfDataStore->set($webform_submission->id(), $mapped_object->sfid());
    $mapped_object->delete();
  }
}

/**
 * Remove broken CJI mapped objects (incorrectly wired up to GLI submissions)
 */
function ilr_deploy_remove_cji_mapped_objects(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $mapped_object_storage = $entity_type_manager->getStorage('salesforce_mapped_object');

  $mapped_objects = $mapped_object_storage->loadByProperties([
    'salesforce_mapping' => [
      'info_req_tp_cji',
    ],
  ]);

  $mapped_object_storage->delete($mapped_objects);
}

/**
 * Transform Salesforce mapped objects for prof ed leads into keystore entries.
 */
function ilr_deploy_transform_prof_ed_interest_lead_mapped_objects(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $mapped_object_storage = $entity_type_manager->getStorage('salesforce_mapped_object');
  $sfDataStore = \Drupal::service('keyvalue')->get('ilr_salesforce.touchpoint.sfid');

  $mapped_objects = $mapped_object_storage->loadByProperties([
    'salesforce_mapping' => [
      'prof_education_interest_leads',
    ],
  ]);

  foreach ($mapped_objects as $mapped_object) {
    $webform_submission = $mapped_object->getMappedEntity();
    $sfDataStore->set($webform_submission->id(), $mapped_object->sfid());
    $mapped_object->delete();
  }
}

/**
 * Delete all touchpoint mappings and mapped objects.
 */
function ilr_deploy_touchpoint_mapping_delete(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $touchpoint_push_mappings = $entity_type_manager->getStorage('salesforce_mapping')->loadPushMappingsByProperties(['salesforce_object_type' => 'Touchpoint__c']);
  $mapped_object_storage = $entity_type_manager->getStorage('salesforce_mapped_object');
  $sfDataStore = \Drupal::service('keyvalue')->get('ilr_salesforce.touchpoint.sfid');

  foreach ($touchpoint_push_mappings as $touchpoint_push_mapping) {
    $mapped_objects = $mapped_object_storage->loadByProperties(['salesforce_mapping' => [$touchpoint_push_mapping->id()]]);

    foreach ($mapped_objects as $mapped_object) {
      $webform_submission = $mapped_object->getMappedEntity();
      $sfDataStore->set($webform_submission->id(), $mapped_object->sfid());
      $mapped_object->delete();
    }

    // This is necessary, even though the config files have been removed,
    // because the mapping config depends on ignored webform config.
    $touchpoint_push_mapping->delete();
  }
}
