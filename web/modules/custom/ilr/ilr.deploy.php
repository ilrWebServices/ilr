<?php

/**
 * @file
 * Drupal hooks provided by the Drush package.
 */

use Drupal\Core\Config\FileStorage;

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
    // Tid 136 is 'High Road' in the Buffalo Colab collection.
    if ((!$item->field_blog_categories->isEmpty() && $item->field_blog_categories->entity->id() === '136') || $item->collection->entity->id() === '55') {
      $item->set('collection', 70);
      // Tid 771 is 'Buffalo' in the High Road collection. tid 773 is 'New York
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

/**
 * Set the text format for all existing project briefs.
 */
function ilr_deploy_set_formats_on_project_briefs(&$sandbox) {
  $node_storage = \Drupal::entityTypeManager()->getStorage('node');
  $project_query = $node_storage->getQuery();
  $project_query->accessCheck(FALSE);
  $project_query->condition('type', 'project');
  $project_ids = $project_query->execute();
  $projects = $node_storage->loadMultiple($project_ids);

  foreach ($projects as $project) {
    $project->body->format = 'standard_formatting';
    $project->field_activities->format = 'standard_formatting';
    $project->field_contact_info_text->format = 'simple_formatting';
    $project->field_partners->format = 'simple_formatting';
    $project->save();
  }
}

/**
 * Delete milr_visit_form_submission_lead salesforce mapping and mapped objects.
 */
function ilr_deploy_milr_visit_form_submission_lead_mapping_delete(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $mapping = $entity_type_manager->getStorage('salesforce_mapping')->load('milr_visit_form_submission_lead');
  $mapped_object_storage = $entity_type_manager->getStorage('salesforce_mapped_object');
  $sfDataStore = \Drupal::service('keyvalue')->get('ilr_salesforce.touchpoint.sfid');
  $mapped_objects = $mapped_object_storage->loadByProperties(['salesforce_mapping' => [$mapping->id()]]);

  foreach ($mapped_objects as $mapped_object) {
    $webform_submission = $mapped_object->getMappedEntity();
    $sfDataStore->set($webform_submission->id(), $mapped_object->sfid());
    $mapped_object->delete();
  }

  // This is necessary, even though the config files have been removed,
  // because the mapping config depends on ignored webform config.
  $mapping->delete();
}

/**
 * Update emeritus persona migrate mappings to manually created personas.
 */
function ilr_deploy_update_emeritus_persona_migration_mappings() {
  $database = \Drupal::database();

  $mappings = [
    1014895 => 529,
    1021426 => 1694,
    1013382 => 1695,
    1000130 => 1699,
    1007100 => 1692,
    1010081 => 1690,
    1008698 => 1696,
    1003458 => 1700,
    1010424 => 1693,
    1014607 => 1701,
    1010719 => 1698,
    1000531 => 1702,
    1002621 => 1703,
  ];

  foreach ($mappings as $source_id => $dest_id) {
    $database->update('migrate_map_ilr_employee_personas')
      ->fields(['destid1' => $dest_id])
      ->condition('sourceid1', $source_id)
      ->execute();
  }
}

/**
 * Swap body field for rich text paragraphs on simple page nodes.
 */
function ilr_deploy_enable_section_rich_text(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $node_storage = $entity_type_manager->getStorage('node');
  $node_query = $node_storage->getQuery();
  $node_query->accessCheck(FALSE);
  $node_query->condition('type', 'simple_page');
  $simple_page_nids = $node_query->execute();
  $simple_pages = $node_storage->loadMultiple($simple_page_nids);
  $paragraph_storage = $entity_type_manager->getStorage('paragraph');

  foreach ($simple_pages as $simple_page) {
    $rich_text_paragraph = $paragraph_storage->create([
      'type' => 'rich_text',
    ]);

    $rich_text_paragraph->field_body->value = $simple_page->body->value;
    $rich_text_paragraph->field_body->format = 'standard_formatting';
    $simple_page->field_components->appendItem($rich_text_paragraph);
    $simple_page->save();
  }
}

/**
 * Install the new kissoff action configuration.
 */
function ilr_deploy_add_kissoff_action() {
  $config_name = 'system.action.ilr_user_kissoff_action';
  $config_path = \Drupal::service('extension.list.module')->getPath('ilr') . '/config/install';
  $config_storage = new FileStorage($config_path);
  $data = $config_storage->read($config_name);

  if ($data) {
    \Drupal::configFactory()->getEditable($config_name)
      ->setData($data)
      ->save(TRUE);
    \Drupal::logger('mymodule')->notice('Adding "ILR user kissoff action" to available actions.');
  }
}

/**
 * Assign employee role to all Users with a profile.
 */
function ilr_deploy_assign_employee_role(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $authmap = \Drupal::service('externalauth.authmap');
  $user_storage = $entity_type_manager->getStorage('user');
  $uids = \Drupal::entityQuery('user')
    ->condition('status', 1)
    ->accessCheck(FALSE)
    ->execute();
  $users = $user_storage->loadMultiple($uids);

  foreach ($users as $user) {
    $netId = $authmap->get($user->id(), 'samlauth');

    $ilr_employee_persona = $entity_type_manager->getStorage('persona')->loadByProperties([
      'type' => 'ilr_employee',
      'status' => 1,
      'field_netid' => $netId,
    ]);

    if (!empty($ilr_employee_persona)) {
      $user->addRole('ilr_employee');
      $user->save();
    }
  }
}

/**
 * Transform paragraphs that use simple columns.
 */
function ilr_deploy_remove_simplecolumns() {
  $simple_column_pids = \Drupal::entityQuery('paragraph')
    ->accessCheck(FALSE)
    ->condition('field_body', 'simple-columns', 'CONTAINS')
    ->execute();

  $simple_column_paragraphs = \Drupal::entityTypeManager()->getStorage('paragraph')->loadMultiple($simple_column_pids);

  /** @var \Drupal\paragraphs\ParagraphInterface $simple_column_paragraph */
  foreach ($simple_column_paragraphs as $simple_column_paragraph) {
    $simple_column_paragraph->field_body->format = 'standard_formatting';
    $simple_column_paragraph->setBehaviorSettings('column_settings', ['columns' => '2']);
    $simple_column_paragraph->save();
  }
}

/**
 * Transform textformats for all collections.
 */
function ilr_deploy_transform_ckeditor4_collections() {
  $type = 'collection';
  $field_name = 'body';
  $storage = \Drupal::entityTypeManager()->getStorage($type);

  $query = $storage->getQuery()
    ->accessCheck(FALSE)
    ->condition($field_name . '.format', ['basic_formatting','basic_formatting_with_media'], 'IN');
  $entity_ids = $query->execute();

  foreach ($storage->loadMultiple($entity_ids) as $entity) {
    $new_format = ($entity->$field_name->format === 'basic_formatting')
      ? 'simple_formatting'
      : 'standard_formatting';
    $entity->$field_name->format = $new_format;
    $entity->save();
  }

  return t('Processed @total collections.', ['@total' => count($entity_ids)]);
}

/**
 * Transform textformats for all nodes.
 */
function ilr_deploy_transform_ckeditor4_nodes(array &$sandbox) {
  $type = 'node';
  $field_name = 'body';
  $batch_size = 50;
  $storage = \Drupal::entityTypeManager()->getStorage($type);

  if (!isset($sandbox['total'])) {
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition($field_name . '.format', ['basic_formatting','basic_formatting_with_media'], 'IN');

    $entity_ids = $query->execute();
    $sandbox['total'] = count($entity_ids);
    $sandbox['ids'] = array_values($entity_ids);
    $sandbox['current'] = 0;
  }

  $to_process = array_slice($sandbox['ids'], $sandbox['current'], $batch_size);

  foreach ($storage->loadMultiple($to_process) as $entity) {
    $new_format = ($entity->$field_name->format === 'basic_formatting')
      ? 'simple_formatting'
      : 'standard_formatting';
    $entity->$field_name->format = $new_format;
    $entity->save();
    $sandbox['current']++;
  }

  $sandbox['#finished'] = ($sandbox['total'] > 0) ? ($sandbox['current'] / $sandbox['total']) : 1;
  return t('Processed @current of @total nodes.', ['@current' => $sandbox['current'], '@total' => $sandbox['total']]);
}

/**
 * Transform textformats for all paragraph items.
 */
function ilr_deploy_transform_ckeditor4_paragraphs(array &$sandbox) {
  $type = 'paragraph';
  $field_name = 'field_body';
  $batch_size = 100;
  $storage = \Drupal::entityTypeManager()->getStorage($type);

  if (!isset($sandbox['total'])) {
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition($field_name . '.format', ['basic_formatting','basic_formatting_with_media'], 'IN');

    $entity_ids = $query->execute();
    $sandbox['total'] = count($entity_ids);
    $sandbox['ids'] = array_values($entity_ids);
    $sandbox['current'] = 0;
  }

  $to_process = array_slice($sandbox['ids'], $sandbox['current'], $batch_size);

  foreach ($storage->loadMultiple($to_process) as $entity) {
    $new_format = ($entity->$field_name->format === 'basic_formatting')
      ? 'simple_formatting'
      : 'standard_formatting';
    $entity->$field_name->format = $new_format;
    $entity->save();
    $sandbox['current']++;
  }

  $sandbox['#finished'] = ($sandbox['total'] > 0) ? ($sandbox['current'] / $sandbox['total']) : 1;
  return t('Processed @current of @total paragraph items.', ['@current' => $sandbox['current'], '@total' => $sandbox['total']]);
}

/**
 * Transform textformats for all terms.
 */
function ilr_deploy_transform_ckeditor4_terms(array &$sandbox) {
  $type = 'taxonomy_term';
  $field_name = 'field_body';
  $batch_size = 100;
  $storage = \Drupal::entityTypeManager()->getStorage($type);

  if (!isset($sandbox['total'])) {
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition($field_name . '.format', ['basic_formatting','basic_formatting_with_media'], 'IN');

    $entity_ids = $query->execute();
    $sandbox['total'] = count($entity_ids);
    $sandbox['ids'] = array_values($entity_ids);
    $sandbox['current'] = 0;
  }

  $to_process = array_slice($sandbox['ids'], $sandbox['current'], $batch_size);

  foreach ($storage->loadMultiple($to_process) as $entity) {
    $new_format = ($entity->$field_name->format === 'basic_formatting')
      ? 'simple_formatting'
      : 'standard_formatting';
    $entity->$field_name->format = $new_format;
    $entity->save();
    $sandbox['current']++;
  }

  $sandbox['#finished'] = ($sandbox['total'] > 0) ? ($sandbox['current'] / $sandbox['total']) : 1;
  return t('Processed @current of @total terms.', ['@current' => $sandbox['current'], '@total' => $sandbox['total']]);
}
