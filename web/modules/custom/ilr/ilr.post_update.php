<?php

/**
 * @file
 * Post update functions for the ILR module.
 */

use Drupal\node\Entity\Node;

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
