<?php

/**
 * @file
 * Post update functions for the ILR module.
 */

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
 * Set alt display for all page nodes without a representative image.
 */
function ilr_post_update_set_page_alt_display_setting(&$sandbox) {
  /** @var \Drupal\pathauto\PathautoGeneratorInterface $pathauto_generator */
  $pathauto_generator = \Drupal::service('pathauto.generator');
  $node_storage = \Drupal::entityTypeManager()->getStorage('node');

  $nids_without_representative_images = $node_storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', 'page')
    ->condition('field_representative_image', NULL, 'IS NULL')
    ->execute();

  $nodes_for_alt_display = $node_storage->loadMultiple($nids_without_representative_images);

  /** @var \Drupal\node\NodeInterface $node */
  foreach ($nodes_for_alt_display as $node) {
    $node->set('behavior_alt_display', 1);
    $node->save();

    // Without this, all updated nodes will have broken path aliases.
    $pathauto_generator->updateEntityAlias($node, 'update');
  }
}
