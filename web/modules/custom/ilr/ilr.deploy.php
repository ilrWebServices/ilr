<?php

/**
 * @file
 * Drupal hooks provided by the Drush package.
 */

use Drupal\Core\Config\FileStorage;
use Drupal\field\Entity\FieldConfig;

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
 * Swap all ckEditor4 asides for the new ckEditor5 version.
 */
function ilr_deploy_replace_ckeditor4_asides(&$sandbox) {
  $aside_pids = \Drupal::entityQuery('paragraph')
    ->accessCheck(FALSE)
    ->condition('field_body', '<aside class="sidebar"', 'CONTAINS')
    ->execute();

  $aside_paragraphs = \Drupal::entityTypeManager()->getStorage('paragraph')->loadMultiple($aside_pids);
  $pattern = '/(<aside class="sidebar">)([\s\S]*?)(<\/aside>)/i';

  foreach ($aside_paragraphs as $aside_paragraph) {
    $replacement = '<blockquote class="aside">$2</blockquote>';
    $result = preg_replace($pattern, $replacement, $aside_paragraph->field_body->value);
    $aside_paragraph->field_body->value = $result;
    $aside_paragraph->save();
  }
}

/**
 * Update all Basic Formatting with Media and Basic Formatting text formats.
 */
function ilr_deploy_update_text_formats(&$sandbox) {
  $entity_type_manager = \Drupal::entityTypeManager();

  if (!isset($sandbox['entities_to_update'])) {
    /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $field_storage_config_storage */
    $field_storage_config_storage = $entity_type_manager->getStorage('field_storage_config');
    $field_storage_configs = $field_storage_config_storage->loadMultipleOverrideFree();

    // An array of formatted text fields with the keys: [entity_type_id][bundle] = [field_names].
    $sandbox['text_fields'] = [];
    $sandbox['entities_to_update'] = [];
    $sandbox['current'] = 0;

    foreach ($field_storage_configs as $field_storage_config) {
      if (in_array($field_storage_config->getType(), ['text_with_summary', 'text_long'])) {
        $entity_type = $field_storage_config->getTargetEntityTypeId();
        $field_name = $field_storage_config->getName();

        foreach ($field_storage_config->getBundles() as $bundle) {
          $sandbox['text_fields'][$entity_type][$bundle][] = $field_name;
        }
      }
    }

    foreach ($sandbox['text_fields'] as $entity_type_id => $bundles) {
      $storage = $entity_type_manager->getStorage($entity_type_id);

      foreach ($bundles as $bundle => $field_names) {
        $query = $storage->getQuery()->accessCheck(FALSE);

        if ($storage->getEntityType()->getKey('bundle')) {
          $query->condition($storage->getEntityType()->getKey('bundle'), $bundle);
        }

        $field_format_group = $query->orConditionGroup();

        foreach ($field_names as $field_name) {
          $field_format_group->condition("{$field_name}.format", ['basic_formatting_with_media', 'basic_formatting'], 'IN');
        }

        $query->condition($field_format_group);

        $entity_ids = $query->execute();

        foreach (array_chunk($entity_ids, 50) as $chunk) {
          $sandbox['entities_to_update'][] = [
            $entity_type_id => $chunk,
          ];
        }
      }
    }

    $sandbox['total'] = count($sandbox['entities_to_update']);
  }

  // Process the rows/chunks.
  foreach ($sandbox['entities_to_update'][$sandbox['current']] as $entity_type_id => $ids) {
    $storage = $entity_type_manager->getStorage($entity_type_id);
    $entities = $storage->loadMultiple($ids);

    foreach ($entities as $entity) {
      $entity_needs_save = FALSE;

      // Loop over field names.
      $field_names = $sandbox['text_fields'][$entity_type_id][$entity->bundle()];

      foreach ($field_names as $field_name) {
        if ($entity->$field_name->isEmpty()) {
          continue;
        }

        // Check the current format of this field.
        $current_format = $entity->$field_name->format;

        // If 'basic_formatting_with_media', set to 'standard_formatting'.
        if ($current_format === 'basic_formatting_with_media') {
          $entity->$field_name->format = 'standard_formatting';
          $entity_needs_save = TRUE;
        }
        // If 'basic_formatting', set to 'simple_formatting'.
        elseif ($current_format === 'basic_formatting') {
          $entity->$field_name->format = 'simple_formatting';
          $entity_needs_save = TRUE;
        }
      }

      // Save this entity. Try to preserve changed time?
      if ($entity_needs_save) {
        $result = $entity->save();
      }
    }

    // Clear the entity cache to save memory.
    $storage->resetCache();

    // Force garbage collection.
    gc_collect_cycles();

    $sandbox['current']++;
  }

  $sandbox['#finished'] = ($sandbox['total'] > 0) ? ($sandbox['current'] / $sandbox['total']) : 1;
  return t('Processed @current of @total chunks.', ['@current' => $sandbox['current'], '@total' => $sandbox['total']]);
}

/**
 * Set the allowed_values format for 'field_body' on all vocabularies.
 */
function ilr_deploy_fix_taxonomy_allowed_values_formats(&$sandbox) {
  $updated_count = 0;
  $allowed_formats = ['standard_formatting', 'simple_formatting'];
  $field_configs = \Drupal::entityTypeManager()
    ->getStorage('field_config')
    ->loadByProperties(['field_name' => 'field_body', 'entity_type' => 'taxonomy_term']);

  foreach ($field_configs as $field_config) {
    /** @var FieldConfig $field_config */
    $field_config->setSetting('allowed_formats', $allowed_formats);

    foreach ($field_config->getThirdPartySettings('allowed_formats') as $key => $value) {
      $field_config->unsetThirdPartySetting('allowed_formats', $key);
    }

    // $field_config->calculateDependencies();
    $field_config->save();
    $updated_count++;
  }

  return t('Updated field_body on @count vocabularies.', ['@count' => $updated_count]);
}
