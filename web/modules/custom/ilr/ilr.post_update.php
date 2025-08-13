<?php

/**
 * @file
 * Post update functions for the ILR module.
 */

use Drupal\node\Entity\Node;
use Drupal\collection\Entity\CollectionItem;

/**
 * Implements hook_removed_post_updates().
 */
function ilr_removed_post_updates() {
  return [
    'ilr_post_update_instructor_photo_alt_attributes' => '9.4.0',
    'ilr_post_update_course_message_block' => '9.4.0',
    'ilr_post_update_course_without_classes_message_block' => '9.4.0',
    'ilr_post_update_rich_text_headings' => '9.4.0',
    'ilr_post_update_node_field_sections_legacy' => '9.4.0',
    'ilr_post_update_add_post_category_terms' => '9.4.0',
    'ilr_post_update_add_covid_blog_category_terms' => '9.4.0',
    'ilr_post_update_fix_blog_category_collection_item_attributes' => '9.4.0',
    'ilr_post_update_add_blog_tag_terms' => '9.4.0',
    'ilr_post_update_fix_node_field_sections_definition' => '9.4.0',
    'ilr_post_update_add_ilrie_publication' => '9.4.0',
    'ilr_post_update_update_post_listing_styles' => '9.4.0',
    'ilr_post_update_update_ilrie_logo' => '9.4.0',
    'ilr_post_update_update_list_styles' => '9.4.0',
    'ilr_post_update_create_social_footer_block' => '9.4.0',
    'ilr_post_update_create_copyright_block' => '9.4.0',
    'ilr_post_update_fix_imported_image_embeds' => '9.4.0',
    'ilr_post_update_create_75_branding_block' => '9.4.0',
    'ilr_post_update_create_75_logo_block' => '9.4.0',
    'ilr_post_update_create_75_video_block' => '9.4.0',
    'ilr_post_update_create_scheinman_logo_block' => '9.4.0',
    'ilr_post_update_create_worker_logo_block' => '9.4.0',
    'ilr_post_update_create_collection_item_aliases' => '9.4.0',
    'ilr_post_update_remove_term_patterns' => '9.4.0',
    'ilr_post_update_move_ilr_in_the_news' => '9.4.0',
    'ilr_post_update_mai_subsite_blog' => '9.4.0',
    'ilr_post_update_node_one_hostile_takeover' => '9.4.0',
    'ilr_post_update_homepage_banner' => '9.4.0',
    'ilr_post_update_update_post_listing_count' => '9.4.0',
    'ilr_post_update_message_blocks' => '9.4.0',
    'ilr_post_update_fix_rich_text_format' => '9.4.0',
    'ilr_post_update_curated_post_listing_references' => '9.4.0',
    'ilr_post_update_add_post_support_to_about_ilr' => '9.4.0',
    'ilr_post_update_unstick_posts' => '9.4.0',
    'ilr_post_update_fix_unstickied_node_aliases' => '9.4.0',
    'ilr_post_update_fix_rich_text_icons' => '9.4.0',
    'ilr_post_update_update_social_footer_block_icons' => '9.4.0',
    'ilr_post_update_set_promo_layouts' => '9.4.0',
    'ilr_post_update_inquiry_form_name_updates' => '9.4.0',
    'ilr_post_update_add_cyber_monday_placeholder' => '9.4.0',
    'ilr_post_update_webform_component_type' => '9.4.0',
    'ilr_post_update_section_frame_setting' => '9.4.0',
    'ilr_post_update_sharp_spring_block' => '9.4.0',
    'ilr_post_update_sharp_spring_forms_block' => '9.4.0',
    'ilr_post_update_section_in_page_fragment_setting' => '9.4.0',
    'ilr_post_update_update_climate_jobs_aliases' => '9.4.0',
    'ilr_post_update_fix_mistaken_climate_jobs_aliases' => '9.4.0',
    'ilr_post_update_set_card_color_schemes' => '9.4.0',
    'ilr_post_update_set_icons' => '9.4.0',
    'ilr_post_update_event_landing_bundle_fields' => '9.4.0',
    'ilr_post_update_update_cahrs_entities' => '9.4.0',
    'ilr_post_update_add_event_keywords_terms_and_migrate_event_listing_behaviors' => '9.4.0',
    'ilr_post_update_certificate_bundle_fields' => '9.4.0',
    'ilr_post_update_add_certificate_mappings' => '9.4.0',
    'ilr_post_update_update_post_listing_collection_setting' => '9.4.0',
    'ilr_post_update_create_ncp_subscription_leads' => '9.4.0',
  ];
}

/**
 * Fix links to CAHRS pdf docs from their legacy system.
 */
function ilr_post_update_fix_imported_cahrs_documents(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $file_storage = $entity_type_manager->getStorage('file');
  $node_storage = $entity_type_manager->getStorage('node');
  $node_query = $node_storage->getQuery();
  $node_query->accessCheck(FALSE);
  $node_query->condition('body', '%est05.esalestrack.com/%', 'LIKE');
  $relevant_nids = $node_query->execute();
  $nodes = $node_storage->loadMultiple($relevant_nids);

  foreach ($nodes as $node) {
    $text_content = $node->body->value;

    if (preg_match_all('/https?:\/\/est05\.esalestrack\.com\/\/?esalestrack\/content\/content.ashx\?(?:aid=2181\&amp;system_filename\=|file=)([^.]+).pdf/mi', $text_content, $matches, PREG_SET_ORDER)) {
      foreach ($matches as $match) {
        $file_query = $file_storage->getQuery();
        $file_query->accessCheck(FALSE);
        $file_query->condition('filename', '%' . $match[1] . '%', 'LIKE');
        $fids = $file_query->execute();

        if (!empty($fids)) {
          $replacement = "/sites/default/files-d8/2023-10/$match[1].pdf";
          $text_content = str_replace($match[0], $replacement, $text_content);
          $node->body->value = $text_content;
          $node->save();
        }
      }
    }
  }

  $paragraph_storage = $entity_type_manager->getStorage('paragraph');
  $paragraphs_query = $paragraph_storage->getQuery();
  $paragraphs_query->accessCheck(FALSE);
  $paragraphs_query->condition('type', 'rich_text');
  $paragraphs_query->condition('field_body', '%est05.esalestrack.com/%', 'LIKE');
  $relevant_paragraph_ids = $paragraphs_query->execute();
  $paragraphs = $paragraph_storage->loadMultiple($relevant_paragraph_ids);

  foreach ($paragraphs as $paragraph) {
    $text_content = $paragraph->field_body->value;

    if (preg_match_all('/https?:\/\/est05\.esalestrack\.com\/\/?esalestrack\/content\/content.ashx\?(?:aid=2181\&amp;system_filename\=|file=)([^.]+).pdf/mi', $text_content, $matches, PREG_SET_ORDER)) {
      foreach ($matches as $match) {
        $file_query = $file_storage->getQuery();
        $file_query->accessCheck(FALSE);
        $file_query->condition('filename', '%' . $match[1] . '%', 'LIKE');
        $fids = $file_query->execute();

        if (!empty($fids)) {
          $replacement = "/sites/default/files-d8/2023-10/$match[1].pdf";
          $text_content = str_replace($match[0], $replacement, $text_content);
          $paragraph->field_body->value = $text_content;
          $paragraph->save();
        }
      }
    }
  }
}

/**
 * Update the LEL collection bundle from blog to subsite_blog.
 */
function ilr_post_update_convert_lel_to_subsite(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $collection = $entity_type_manager->getStorage('collection')->load(44);
  $collection_machine_name = 'subsite-' . $collection->id();
  $collection_item_storage = $entity_type_manager->getStorage('collection_item');

  // Create the subsite menu.
  $menu = $entity_type_manager->getStorage('menu')->create([
    'langcode' => 'en',
    'status' => TRUE,
    'id' => $collection_machine_name,
    'label' => $collection->label() . ' subsite main navigation',
    'description' => 'Auto-generated menu for ' . $collection->label() . ' subsite',
  ]);
  $menu->save();

  // Add the menu to the collection.
  $collection_item_menu = $collection_item_storage->create([
    'type' => 'default',
    'collection' => $collection->id(),
    'weight' => 10,
  ]);
  $collection_item_menu->item = $menu;
  $collection_item_menu->setAttribute('subsite_collection_id', $collection->id());
  $collection_item_menu->save();

  // Create a block visibility group.
  $bvg_storage = $entity_type_manager->getStorage('block_visibility_group');
  $bvg = $bvg_storage->create([
    'label' => $collection->label() . ' subsite',
    'id' => str_replace('-', '_', $collection_machine_name),
    'logic' => 'and',
  ]);

  // Add the subsite collection path to the BVG as a condition.
  $bvg->addCondition([
    'id' => 'request_path',
    'pages' => $collection->toUrl()->toString() . '*',
    'negate' => FALSE,
    'context_mapping' => [],
  ]);

  $bvg->save();

  // Add the bvg to this new collection.
  $collection_item_bvg = $collection_item_storage->create([
    'type' => 'default',
    'collection' => $collection->id(),
    'weight' => 10,
  ]);
  $collection_item_bvg->item = $bvg;
  $collection_item_bvg->setAttribute('subsite_collection_id', $collection->id());
  $collection_item_bvg->save();

  // Add the new menu block to the header region of the new
  // block visibility group.
  $block_storage = $entity_type_manager->getStorage('block');
  $default_theme = \Drupal::service('theme_handler')->getDefault();
  $subsite_menu_block = $block_storage->create([
    'id' => $default_theme . '_menu_' . str_replace('-', '_', $collection_machine_name),
    'plugin' => 'system_menu_block:' . $collection_machine_name,
    'theme' => $default_theme,
    'region' => 'header',
    'settings' => [
      'label' => $collection->label() . ' menu block',
      'label_display' => FALSE,
    ],
    'weight' => 100,
  ]);
  $subsite_menu_block->setVisibilityConfig('condition_group', [
    'id' => 'condition_group',
    'negate' => FALSE,
    'block_visibility_group' => $bvg->id(),
  ]);
  $subsite_menu_block->save();

  // Update the collection type.
  $collection->type = 'subsite_blog';
  $collection->save();
}

/**
 * Set a default value for the suppress listings field on event landing pages.
 */
function ilr_post_update_update_suppress_listing_values(&$sandbox) {
  $query = \Drupal::entityQuery('node');
  $query->accessCheck(FALSE);
  $query->condition('type', 'event_landing_page');
  $nids = $query->execute();
  $landing_pages = Node::loadMultiple($nids);

  foreach ($landing_pages as $landing_page) {
    $landing_page->set('behavior_suppress_listing', 0);
    $landing_page->save();
  }
}

/**
 * Update the path aliases for YTI project nodes.
 */
function ilr_post_update_update_yti_project_aliases(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $collection_item_storage = $entity_type_manager->getStorage('collection_item');

  $project_items = $collection_item_storage->loadByProperties([
    'type' => 'project_item',
    'collection' => '57',
  ]);

  // Save the node to update the alias.
  foreach ($project_items as $project_item) {
    $project_item->item->entity->save();
  }
}

/**
 * Add YTI-related persona tags.
 */
function ilr_post_update_add_yti_persona_tags_vocab(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $yti_collection = $entity_type_manager->getStorage('collection')->load(57);
  $vocab = $entity_type_manager->getStorage('taxonomy_vocabulary')->create([
    'langcode' => 'en',
    'status' => TRUE,
    'name' => $yti_collection->label() . ' persona tags',
    'vid' => 'collection_' . $yti_collection->id() . '_persona_tags',
    'description' => 'Auto-generated vocabulary for ' . $yti_collection->label() . ' persona tags',
  ]);
  $vocab->save();

  if ($vocab) {
    // Create the director term and add both the vocab and term to the YTI
    // collection.
    $director_term = $entity_type_manager->getStorage('taxonomy_term')->create([
      'name' => 'Director',
      'vid' => $vocab->id(),
    ]);
    $director_term->save();

    $collection_item_storage = $entity_type_manager->getStorage('collection_item');
    $collection_item_vocab = $collection_item_storage->create([
      'type' => 'default',
      'collection' => $yti_collection->id(),
      'canonical' => TRUE,
      'weight' => 10,
    ]);
    $collection_item_vocab->item = $vocab;
    $collection_item_vocab->setAttribute('collection_persona_tags', $vocab->id());
    $collection_item_vocab->save();

    $collection_item_term = $collection_item_storage->create([
      'type' => 'default',
      'collection' => $yti_collection->id(),
      'canonical' => TRUE,
    ]);
    $collection_item_term->item = $director_term;
    $collection_item_term->save();
  }
}

/**
 * Unpublish older CAHRS Resource Library items, based on created date and type.
 */
function ilr_post_update_unpublish_older_cahrs_media(&$sandbox) {
  $cutoff_dates_by_type = [
    'News' => strtotime('31 December 2022'),
    'Working Group Summaries' => strtotime('31 December 2020'),
    'Research' => strtotime('31 December 2018'),
  ];

  $query = \Drupal::entityQuery('collection_item');
  $query->accessCheck(FALSE);
  $query->condition('type', 'resource_library_item');
  $query->condition('field_resource_type', array_keys($cutoff_dates_by_type), 'IN');
  // Only get documents that are old enough to qualify.
  $query->condition('created', $cutoff_dates_by_type['News'], '<=' );
  $cids = $query->execute();
  $document_post_items = CollectionItem::loadMultiple($cids);

  foreach ($document_post_items as $document_post_item) {
    $type = $document_post_item->field_resource_type->value;

    if ($document_post_item->created->value > $cutoff_dates_by_type[$type]) {
      continue;
    }

    $document_post_item->item->entity->set('status', 0)->save();
  }
}

/**
 * Remove newsletter submission to lead mapped objects.
 */
function ilr_post_update_remove_newsletter_lead_mapped_objects(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $mapped_object_storage = $entity_type_manager->getStorage('salesforce_mapped_object');

  $mapped_objects = $mapped_object_storage->loadByProperties([
    'salesforce_mapping' => [
      'buffalo_colab_newsletter_lead',
      'ncp_newsletter_lead',
      'scheinman_newsletter_lead',
      'worker_institute_news_lead',
    ],
  ]);

  foreach ($mapped_objects as $mapped_object) {
    $mapped_object->delete();
  }
}

/**
 * Merge duplicate event sponsors.
 */
function ilr_post_update_merge_dupe_event_sponsors(&$sandbox) {
  // These term ids are dupes in the event_sponsors vocabulary. We'll keep the
  // first one and merge the rest.
  $dupes = [
    [351,346,484,494],
    [349,350,483,496],
    [329,345],
  ];

  $entity_type_manager = \Drupal::service('entity_type.manager');
  $term_storage = $entity_type_manager->getStorage('taxonomy_term');
  $node_storage = $entity_type_manager->getStorage('node');

  foreach ($dupes as $dupe_tids) {
    $keeper_tid = array_shift($dupe_tids);

    // Update all event landing page sponsor references.
    $node_query = $node_storage->getQuery();
    $node_query->accessCheck(FALSE);
    $node_query->condition('type', 'event_landing_page');
    $node_query->condition('field_sponsor', $dupe_tids, 'IN');
    $node_results = $node_query->execute();
    $nodes = $node_storage->loadMultiple($node_results);

    foreach ($nodes as $node) {
      $values = $node->field_sponsor->getValue();

      foreach ($values as &$value) {
        if (in_array($value['target_id'], $dupe_tids)) {
          $value['target_id'] = $keeper_tid;
        }
      }

      $node->field_sponsor->setValue($values);
      $node->save();
    }

    // Load the dupe terms.
    $dupe_terms = $term_storage->loadMultiple($dupe_tids);

    // Delete the dupe terms.
    $term_storage->delete($dupe_terms);
  }
}

/**
 * Update the NCRS collection bundle from content_section to subsite_blog.
 */
function ilr_post_update_convert_ncrs_to_subsite(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $collection = $entity_type_manager->getStorage('collection')->load(72);
  $collection_machine_name = 'subsite-' . $collection->id();
  $collection_item_storage = $entity_type_manager->getStorage('collection_item');

  // Create the subsite menu.
  $menu = $entity_type_manager->getStorage('menu')->create([
    'langcode' => 'en',
    'status' => TRUE,
    'id' => $collection_machine_name,
    'label' => $collection->label() . ' subsite main navigation',
    'description' => 'Auto-generated menu for ' . $collection->label() . ' subsite',
  ]);
  $menu->save();

  // Move the old menu_link_content items to the new menu.
  $mlc_query = $entity_type_manager->getStorage('menu_link_content')->getQuery();
  $mlc_query->accessCheck(FALSE);
  $mlc_query->condition('menu_name', 'section-72');
  $mlc_results = $mlc_query->execute();
  $mlc_entities = $entity_type_manager->getStorage('menu_link_content')->loadMultiple($mlc_results);

  foreach ($mlc_entities as $mlc_entity) {
    $mlc_entity->menu_name = 'subsite-72';
    $mlc_entity->save();
  }

  // Add the menu to the collection.
  $collection_item_menu = $collection_item_storage->create([
    'type' => 'default',
    'collection' => $collection->id(),
    'weight' => 10,
  ]);
  $collection_item_menu->item = $menu;
  $collection_item_menu->setAttribute('subsite_collection_id', $collection->id());
  $collection_item_menu->save();

  // Create a block visibility group.
  $bvg_storage = $entity_type_manager->getStorage('block_visibility_group');
  $bvg = $bvg_storage->create([
    'label' => $collection->label() . ' subsite',
    'id' => str_replace('-', '_', $collection_machine_name),
    'logic' => 'and',
  ]);

  // Add the subsite collection path to the BVG as a condition.
  $bvg->addCondition([
    'id' => 'request_path',
    'pages' => $collection->toUrl()->toString() . '*',
    'negate' => FALSE,
    'context_mapping' => [],
  ]);

  $bvg->save();

  // Add the bvg to this new collection.
  $collection_item_bvg = $collection_item_storage->create([
    'type' => 'default',
    'collection' => $collection->id(),
    'weight' => 10,
  ]);
  $collection_item_bvg->item = $bvg;
  $collection_item_bvg->setAttribute('subsite_collection_id', $collection->id());
  $collection_item_bvg->save();

  // Add the new menu block to the header region of the new
  // block visibility group.
  $block_storage = $entity_type_manager->getStorage('block');
  $default_theme = \Drupal::service('theme_handler')->getDefault();
  $subsite_menu_block = $block_storage->create([
    'id' => $default_theme . '_menu_' . str_replace('-', '_', $collection_machine_name),
    'plugin' => 'system_menu_block:' . $collection_machine_name,
    'theme' => $default_theme,
    'region' => 'header',
    'settings' => [
      'label' => $collection->label() . ' menu block',
      'label_display' => FALSE,
    ],
    'weight' => 100,
  ]);
  $subsite_menu_block->setVisibilityConfig('condition_group', [
    'id' => 'condition_group',
    'negate' => FALSE,
    'block_visibility_group' => $bvg->id(),
  ]);
  $subsite_menu_block->save();

  // Update the collection type.
  $collection->type = 'subsite_blog';
  $collection->save();
}

/**
 * Create new homepage banner component content block.
 */
function ilr_post_update_create_homepage_banner_component_block(&$sandbox) {
  $blockEntityManager = \Drupal::service('entity_type.manager')
    ->getStorage('block_content');

  $block = $blockEntityManager->create([
    'type' => 'component',
    'uuid' => 'cd73985d-a508-4e02-bcc9-51b6262c4c99',
    'label_display' => 0,
  ]);

  $block->info = "Homepage Banner";
  $block->save();
}
