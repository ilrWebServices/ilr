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
 * Merge duplicate event sponsors.
 */
function ilr_post_update_create_employee_redirects(&$sandbox) {
  $d7_aliases = [
    'bsp2' =>	'/people/bonnie-hockenberry',
    'jph3' =>	'/people/jonathan-horn',
    'cgh2' =>	'/people/caroline-hecht',
    'mt22' =>	'/people/miguelina-tabar',
    'djc5' =>	'/people/dorothy-carlson',
    'vn11' =>	'/people/vanessa-ng',
    'wae1' =>	'/people/william-erickson',
    'pab7' =>	'/people/peter-bamberger',
    'mc31' =>	'/people/marcia-clark',
    'amw3' =>	'/people/angela-wagner',
    'mmh5' =>	'/people/melissa-holland',
    'lmk8' =>	'/people/lynn-king',
    'mes20' =>	'/people/michele-secord',
    'lal8' =>	'/people/laura-lewis',
    'dgl1' =>	'/people/david-lippincott',
    'jds13' =>	'/people/jennifer-dean',
    'lsg3' =>	'/people/linda-gasser',
    'sre2' =>	'/people/sara-edwards',
    'mls21' =>	'/people/martha-smith',
    'amh11' =>	'/people/ann-herson',
    'mlh15' =>	'/people/marcia-harding-zeller',
    'rmd5' =>	'/people/regina-duffey-moravek',
    'dwf3' =>	'/people/deborah-fisher',
    'lec6' =>	'/people/lisa-rose',
    'jmr21' =>	'/people/joan-roberts',
    'bam20' =>	'/people/bruce-martinelli',
    'met8' =>	'/people/maria-emilia-simonet',
    'sca3' =>	'/people/sol-agosto',
    'dfk3' =>	'/people/deborah-king',
    'dhg3' =>	'/people/dh-goodall',
    'dms44' =>	'/people/donna-stone',
    'an25' =>	'/people/anuradha-lyons',
    'ceh19' =>	'/people/cynthia-hayes',
    'lv19' =>	'/people/larisa-vygran',
    'pes5' =>	'/people/pamela-sackett',
    'jpj6' =>	'/people/joanne-kenyon',
    'vmd2' =>	'/people/victor-diaz',
    'jt56' =>	'/people/jeffery-teeter',
    'hw33' =>	'/people/holly-ogden',
    'csv3' =>	'/people/catherine-vellake',
    'jmd44' =>	'/people/julie-dean',
    'mcc25' =>	'/people/michael-colunio',
    'kfh4' =>	'/people/kevin-f-harris',
    'jjm42' =>	'/people/john-moody',
    'fjp2' =>	'/people/john-peters',
    'pd33' =>	'/people/patrick-domaratz',
    'kn64' =>	'/people/katrina-nobles',
    'ms286' =>	'/people/melissa-snyder',
    'mr262' =>	'/people/michael-radzik',
    'cgl8' =>	'/people/camille-lee',
    'day4' =>	'/people/david-yantorno',
    'smc64' =>	'/people/sariena-lam',
    'dd86' =>	'/people/dianna-dean-tucker',
    'eda8' =>	'/people/edwin-acevedo',
    'blm2' =>	'/people/barbara-morley',
    'smb6' =>	'/people/stuart-basefsky',
    'crm3' =>	'/people/cathleen-sheils',
    'vmb2' =>	'/people/vernon-briggs',
    'dsl6' =>	'/people/debra-lamb-deans',
    'ps39' =>	'/people/patrizia-sione',
    'sew13' =>	'/people/susan-woods',
    'gab7' =>	'/people/george-blair',
    'atm5' =>	'/people/arthur-matthews',
    'sac29' =>	'/people/suzanne-cohen',
    'rjl22' =>	'/people/richard-lipsitz',
    'hsk11' =>	'/people/henry-kramer',
    'eck4' =>	'/people/edward-kokkelenberg',
    'wmb2' =>	'/people/william-briggs',
    'cnw3' =>	'/people/christine-wais',
    'swm5' =>	'/people/susan-mittler',
    'lsf9' =>	'/people/lorraine-sellen-gross',
    'cc336' =>	'/people/christine-cotton',
    'jz76' =>	'/people/joseph-zappala',
    'tm63' =>	'/people/theresa-mohabir',
    'ems65' =>	'/people/erin-sember-chase',
    'ek99' =>	'/people/ellen-fitchette',
    'em244' =>	'/people/edward-martinez',
    'ss446' =>	'/people/susan-sappington',
    'dmr47' =>	'/people/donna-ramil',
    'jlw85' =>	'/people/jennifer-morley',
    'ims25' =>	'/people/ian-schachner',
    'mjk53' =>	'/people/mary-garin',
    'mb387' =>	'/people/mallika-banerjee',
    'dpw27' =>	'/people/dustin-weber',
    'cja3' =>	'/people/candace-akins',
    'djc88' =>	'/people/jason-carpenter',
    'jdt35' =>	'/people/jeffrey-trondsen',
    'sja35' =>	'/people/stephen-adler',
    'mct38' =>	'/people/michael-tsipouroglou',
    'rmr46' =>	'/people/richard-romano',
    'ram96' =>	'/people/robert-molofsky',
    'lra29' =>	'/people/laura-robinson',
    'mls266' =>	'/people/michelle-podolec',
    'djk56' =>	'/people/donald-kenyon',
    'gml28' =>	'/people/gloria-loehle',
    'kt264' =>	'/people/kristie-lamb',
    'sam269' =>	'/people/silvia-medina',
    'lb274' =>	'/people/lorraine-biechele',
    'mh75' =>	'/people/michael-hollenbeck',
    'sdh46' =>	'/people/seth-harris',
    'djf59' =>	'/people/daniel-fisher',
    'rad83' =>	'/people/robin-driskel',
    'rdf53' =>	'/people/richard-fincher',
    'rb476' =>	'/people/rodney-bouchey',
    'ajd88' =>	'/people/antonio-de-ridder',
    'jlw297' =>	'/people/jennifer-weidner',
    'mh598' =>	'/people/michael-haflett',
    'dmb347' =>	'/people/donald-bazley',
    'rm527' =>	'/people/randall-miles',
    'mc834' =>	'/people/mary-catt',
    'ct372' =>	'/people/charles-tharp',
    'cl672' =>	'/people/curtis-lyons',
    'cct48' =>	'/people/claire-velasquez',
    'dm484' =>	'/people/david-matteson',
    'kms342' =>	'/people/kathryn-steigerwalt',
    'aaw43' =>	'/people/allison-weiner-heinemann',
    'vbm5' =>	'/people/valerie-malzer',
    'brb76' =>	'/people/brett-blanchard',
    'wjb93' =>	'/people/william-briggs-0',
    'hl647' =>	'/people/hyuck-jae-lee',
    'mww59' =>	'/people/michael-ward',
    'mpw68' =>	'/people/margaret-graber',
    'mlc322' =>	'/people/megan-connelly',
    'jrw354' =>	'/people/joseph-williams',
    'kts47' =>	'/people/karen-siewert',
    'lrh62' =>	'/people/louis-hyman',
    'rm743' =>	'/people/richard-mansfield',
    'jml527' =>	'/people/jacob-lopez',
    'mw725' =>	'/people/mo-wang',
    'edf48' =>	'/people/eli-friedman',
    'vlp33' =>	'/people/victoria-prowse',
    'sam387' =>	'/people/steven-miranda',
    'kbh55' =>	'/people/katherine-howe',
    'lc638' =>	'/people/lisa-csencsits',
    'ep386' =>	'/people/elona-pira',
    'asg255' =>	'/people/aliqae-geraci',
    'atf46' =>	'/people/aaron-froehlich',
    'jc2666' =>	'/people/jamie-canfield',
    'mhj47' =>	'/people/michael-james',
    'es656' =>	'/people/ellice-switzer',
    'amr348' =>	'/people/andrea-rose',
    'klh249' =>	'/people/kathryn-hiney',
    'wes226' =>	'/people/william-stringer',
    'ajw272' =>	'/people/amanda-washburn',
    'wbl32' =>	'/people/wilma-liebman',
    'cb667' =>	'/people/christina-boryk',
    'smd289' =>	'/people/sara-devault-feldman',
    'mcs378' =>	'/people/matthew-saleh',
    'tls245' =>	'/people/timothy-senft',
    'aor4' =>	'/people/armands-revelins',
    'st725' =>	'/people/shiloh-thomas',
    'nlh47' =>	'/people/nicole-heasley',
    'tb353' =>	'/people/theo-black',
    'oo43' =>	'/people/olivia-owens',
    'lar284' =>	'/people/laurie-rocker',
    'msa224' =>	'/people/michelle-alvord',
    'ch662' =>	'/people/carolina-harris',
    'jgj52' =>	'/people/janet-jayne',
    'myw25' =>	'/people/melanie-willingham-jaggers',
    'dmp268' =>	'/people/david-prosten',
    'cdr82' =>	'/people/christopher-rolling',
    'al2269' =>	'/people/alexandria-lee',
    'hac95' =>	'/people/heidi-copeland',
    'es842' =>	'/people/erin-sutzko',
    'pcm83' =>	'/people/patricia-martin-holdridge',
    'xl535' =>	'/people/xia-li',
    'jlp359' =>	'/people/jennifer-perry',
    'jam886' =>	'/people/jennifer-mimno',
    'kjb245' =>	'/people/kyle-brumsted',
    'clj64' =>	'/people/christine-jones',
    'rka45' =>	'/people/rachel-aleks',
    'mm2689' =>	'/people/monica-mcgavin',
    'ps828' =>	'/people/prerna-sampat',
    'ale52' =>	'/people/allison-elias',
    'skb99' =>	'/people/sarah-brewer',
    'lb542' =>	'/people/lindsay-bing',
    'yc526' =>	'/people/yane-hao-chen',
    'jdm327' =>	'/people/john-meany',
    'ash239' =>	'/people/alexandra-hinck',
    'xy77' =>	'/people/xiaoyan-yuan',
    'rf357' =>	'/people/rachel-fichter',
    'ad592' =>	'/people/anouchka-dybal',
    'sd536' =>	'/people/stephen-dangelo',
    'sep225' =>	'/people/sara-palmer',
    'mv338' =>	'/people/mariana-viollaz',
    'djc397' =>	'/people/darius-conger',
    'fs332' =>	'/people/francesco-seghezzi',
    'dlc283' =>	'/people/danielle-collier',
    'hs258' =>	'/people/haley-singer',
    'jma7' =>	'/people/john-abowd',
    'sja11' =>	'/people/sandra-acevedo',
    'lha1' =>	'/people/lee-adler',
    'sma21' =>	'/people/sally-alvarez',
    'ra40' =>	'/people/ronald-applegate',
    'ja395' =>	'/people/john-august',
    'odb2' =>	'/people/opal-bablington',
    'sb22' =>	'/people/samuel-bacharach',
    'ljb239' =>	'/people/linda-barrington',
    'rb41' =>	'/people/rosemary-batt',
    'bk30' =>	'/people/brigid-beachler',
    'bb92' =>	'/people/bradford-bell',
    'eb582' =>	'/people/elena-gitter',
    'mlb363' =>	'/people/marya-besharov',
    'erb4' =>	'/people/esta-bigler',
    'jpb6' =>	'/people/jeffrey-bishop',
    'mjb62' =>	'/people/melissa-bjelland',
    'fdb4' =>	'/people/francine-blau',
    'cjb39' =>	'/people/carol-blessing',
    'vkb28' =>	'/people/vanessa-bohns',
    'grb3' =>	'/people/george-boyer',
    'swb6' =>	'/people/susan-brecher',
    'drb22' =>	'/people/david-brewer',
    'kb41' =>	'/people/kathleen-briggs',
    'klb23' =>	'/people/kate-bronfenbrenner',
    'njb7' =>	'/people/nellie-brown',
    'smb23' =>	'/people/susanne-bruyere',
    'jab18' =>	'/people/john-bunge',
    'mb376' =>	'/people/melissa-burress',
    'pjw4' =>	'/people/pamela-staub',
    'mdb238' =>	'/people/m-diane-burton',
    'ljc46' =>	'/people/legna-cabrera',
    'mc64' =>	'/people/marcia-calicchia',
    'gc32' =>	'/people/gene-carroll',
    'rac79' =>	'/people/raymond-cebula',
    'rlc29' =>	'/people/rhonda-clouse',
    'lsc4' =>	'/people/lynn-coffey-edelman',
    'dc489' =>	'/people/daniel-cohen',
    'cjc53' =>	'/people/christopher-collins',
    'ajc22' =>	'/people/alexander-colvin',
    'lac24' =>	'/people/lance-compa',
    'mlc13' =>	'/people/maria-lorena-cook',
    'lhc62' =>	'/people/lawanda-cook',
    'jrc32' =>	'/people/jefferson-cowie',
    'jdd10' =>	'/people/jim-delrosso',
    'ddd1' =>	'/people/david-demello',
    'iad1' =>	'/people/ileen-devault',
    'tjd9' =>	'/people/thomas-diciccio',
    'gld4' =>	'/people/gwyneth-dobson',
    'lhd4' =>	'/people/linda-donahue',
    'ld284' =>	'/people/lisa-dragoni',
    'ldd3' =>	'/people/lee-dyer',
    'rge2' =>	'/people/ronald-ehrenberg',
    'de21' =>	'/people/daniel-elswit',
    'he76' =>	'/people/hassan-enayati',
    'gsf2' =>	'/people/gary-fields',
    'mcf22' =>	'/people/maria-figueroa',
    'dmf22' =>	'/people/david-filiberto',
    'ljf8' =>	'/people/lou-jean-fleron',
    'lac53' =>	'/people/laura-georgianna',
    'smg338' =>	'/people/shannon-gleeson',
    'meg3' =>	'/people/michael-gold',
    'tpg3' =>	'/people/thomas-golden',
    'sbg13' =>	'/people/stephen-gollnick',
    'jag97' =>	'/people/jack-goncalo',
    'jmg30' =>	'/people/jeffrey-grabelsky',
    'jeg68' =>	'/people/joseph-grasso',
    'lsg7' =>	'/people/lois-gray',
    'kg275' =>	'/people/kati-griffith',
    'jag28' =>	'/people/james-gross',
    'jag522' =>	'/people/jarra-gruen',
    'jjh56' =>	'/people/john-haggerty',
    'jap7' =>	'/people/josephine-hagin',
    'ldh44' =>	'/people/laura-haley',
    'srh25' =>	'/people/shelly-hall',
    'kfh7' =>	'/people/kevin-hallock',
    'thh2' =>	'/people/tove-hammer',
    'mlh14' =>	'/people/melissa-harrington',
    'jph42' =>	'/people/john-hausknecht',
    'rh363' =>	'/people/ruthann-heath',
    'lh386' =>	'/people/laura-hertzog',
    'nah36' =>	'/people/nancy-hinkley',
    'cmg4' =>	'/people/christina-homrighouse',
    'msh46' =>	'/people/maria-hopko',
    'rwh8' =>	'/people/richard-hurd',
    'rmh2' =>	'/people/robert-hutchens',
    'gj10' =>	'/people/george-jakubson',
    'jd51' =>	'/people/jasminy-joe',
    'lmk12' =>	'/people/lawrence-kahn',
    'ek368' =>	'/people/m-karns',
    'ak564' =>	'/people/arun-karpur',
    'hck2' =>	'/people/harry-katz',
    'mmk48' =>	'/people/mithila-khounthavong',
    'slk12' =>	'/people/sally-klingel',
    'sck4' =>	'/people/sarosh-kuruvilla',
    'ejl3' =>	'/people/edward-j-lawler',
    'pml5' =>	'/people/peter-lazes',
    'kal238' =>	'/people/kathleen-lee',
    'rll5' =>	'/people/risa-lieberwitz',
    'dbl4' =>	'/people/david-lipsky',
    'al2284' =>	'/people/adam-seth-litwin',
    'bal93' =>	'/people/beth-livingston',
    'cal326' =>	'/people/christine-long',
    'ejl44' =>	'/people/edwin-lopez-soto',
    'kkm74' =>	'/people/katherine-macdowell',
    'gm467' =>	'/people/giselle-maira',
    'kam47' =>	'/people/kenneth-margolies',
    'vm248' =>	'/people/verónica-martínez-matsuda',
    'dm627' =>	'/people/daniel-mccray',
    'cjm267' =>	'/people/christian-miller',
    'rlo4' =>	'/people/renee-monroe',
    'vam6' =>	'/people/veronica-moore',
    'sm92' =>	'/people/sherrie-morales',
    'tlm6' =>	'/people/traci-morse',
    'zen2' =>	'/people/zafar-nazarov',
    'smn33' =>	'/people/samuel-nelson',
    'lhn5' =>	'/people/lisa-nishii',
    'dss7' =>	'/people/darrlyn-oconnell',
    'sao35' =>	'/people/susan-oneil',
    'nr52' =>	'/people/nathan-reimer',
    'sjr29' =>	'/people/stacy-reynolds',
    'cr443' =>	'/people/chris-riddell',
    'jr557' =>	'/people/janet-rizzuto',
    'klr3' =>	'/people/katherine-roberts',
    'hhr5' =>	'/people/hannah-rudstam',
    'nas4' =>	'/people/nick-salvatore',
    'rms43' =>	'/people/rocco-scanza',
    'rs60' =>	'/people/ronald-seeber',
    'ms924' =>	'/people/michael-serino',
    'dms499' =>	'/people/donna-sharp',
    'acs5' =>	'/people/anne-sieverding',
    'lrs95' =>	'/people/lara-skinner',
    'rss14' =>	'/people/robert-smith',
    'kjs275' =>	'/people/katherine-solis-fonte',
    'wjs7' =>	'/people/william-sonnenstuhl',
    'ws283' =>	'/people/wendy-strobel-gower',
    'ss2353' =>	'/people/stephanie-sutow',
    'ss266' =>	'/people/sean-sweeney',
    'srt82' =>	'/people/stephanie-thomas',
    'pst3' =>	'/people/pamela-tolbert',
    'amb27' =>	'/people/alice-torres',
    'pit2' =>	'/people/phela-townsend',
    'lrt4' =>	'/people/lowell-turner',
    'sav22' =>	'/people/sara-van-looy',
    'pfv2' =>	'/people/paul-velleman',
    'lv39' =>	'/people/lars-vilhuber',
    'kcw8' =>	'/people/kc-wagner',
    'mtw1' =>	'/people/martin-wells',
    'acw18' =>	'/people/arthur-wheaton',
    'mw326' =>	'/people/michele-williams',
    'thw3' =>	'/people/theresa-woodhouse',
    'pmw6' =>	'/people/patrick-wright',
    'jy335' =>	'/people/judy-young',
    'emz34' =>	'/people/emily-zitek',
    'sv282' =>	'/people/sarah-von-schrader',
    'pec1' =>	'/people/patricia-campos-medina',
    'peq2' =>	'/people/peter-quinn',
    'emh255' =>	'/people/eryn-halladay',
    'hbb42' =>	'/people/harry-bronson',
    'pin5' =>	'/people/patrick-newby',
    'omk4' =>	'/people/olga-khessina',
    'tsi4' =>	'/people/tamara-ingram',
    'gjc52' =>	'/people/george-casolari',
    'cfw58' =>	'/people/cornell-f-woodson',
    'dg633' =>	'/people/david-gill',
    'ac286' =>	'/people/arkadev-chatterjea',
    'srb242' =>	'/people/sarah-berger',
    'ash258' =>	'/people/aidan-holland-loucks',
    'br277' =>	'/people/brendan-rogan',
    'dk697' =>	'/people/david-kasiarz',
    'cs986' =>	'/people/christopher-sweet',
    'mm2739' =>	'/people/michelle-miller',
    'sl2667' =>	'/people/sungkyun-lee',
    'wjc24' =>	'/people/william-cote',
    'jt693' =>	'/people/jeffrey-tamburo',
    'lz379' =>	'/people/long-zhang',
    'ky348' =>	'/people/kyoung-hee-yu',
    'joc22' =>	'/people/jacklyn-creque',
    'dr64' =>	'/people/damone-richardson',
    'sm2559' =>	'/people/sara-martinez-de-morentin',
    'mck223' =>	'/people/michael-konopacki',
    'jd32' =>	'/people/jacquelin-drucker',
    'so44' =>	'/people/shaul-oreg',
    'wdw65' =>	'/people/william-wunder',
    'gmc74' =>	'/people/gabriel-cassillo',
    'acw38' =>	'/people/angela-winfield',
    'aca27' =>	'/people/ariel-avgar',
    'sg673' =>	'/people/sonia-guior',
    'alz35' =>	'/people/allison-zdunczyk',
    'raj96' =>	'/people/rebecca-jakubson',
    'ig93' =>	'/people/irina-gaynanova',
    'jed276' =>	'/people/jonathan-dawson',
    'usa2' =>	'/people/usamah-andrabi',
    'mak462' =>	'/people/matthew-kehoe',
    'jeh268' =>	'/people/jessica-withers',
    'sc729' =>	'/people/sun-wook-chung',
    'cjv42' =>	'/people/cynthia-vincens',
    'wjs267' =>	'/people/william-staudenmeier',
    'vld7' =>	'/people/virginia-doellgast',
    'icg2' =>	'/people/ian-greer',
    'jhk296' =>	'/people/jr-keller',
    'bar93' =>	'/people/ben-rissing',
    'pd359' =>	'/people/paul-davis',
    'jem543' =>	'/people/john-mccarthy',
    'mmt88' =>	'/people/mallika-thomas',
    'mb2497' =>	'/people/marco-biasi',
    'lkc48' =>	'/people/laura-caruso',
    'sk365' =>	'/people/sunghoon-kim',
    'nlg43' =>	'/people/norma-gunn',
    'dcc258' =>	'/people/duncan-campbell',
    'jd865' =>	'/people/jae-hyung-do',
    'md834' =>	'/people/mei-dong',
    'cfo26' =>	'/people/crystal-ogden',
    'bmd89' =>	'/people/bryan-dufresne',
    'met239' =>	'/people/megan-towles',
    'jgb53' =>	'/people/john-bickerman',
    'ac2594' =>	'/people/adriana-cruz',
    'lc737' =>	'/people/luis-candelaria',
    'ne94' =>	'/people/nazanin-eftekhari',
    'jrb458' =>	'/people/jacob-barnes',
    'jb958' =>	'/people/julia-bartosch',
    'lrb67' =>	'/people/lauren-koskinen',
    'js184' =>	'/people/janette-coppage',
    'mp647' =>	'/people/marcus-poppen',
    'ls672' =>	'/people/lisa-stern',
    'ts33' =>	'/people/tal-simons',
    'kjb236' =>	'/people/krista-bowen',
    'eef9' =>	'/people/beth-flynn-ferry',
    'ked89' =>	'/people/kathryn-dahm',
    'kjr62' =>	'/people/kimberly-ramsay',
    'sah334' =>	'/people/sophia-harmon',
    'tn295' =>	'/people/tsz-fung-kenneth-ng',
    'st789' =>	'/people/siqi-tu',
    'jmc682' =>	'/people/jenny-coronel',
    'mhw77' =>	'/people/marcia-watt',
    'mav29' =>	'/people/michelle-vandebogart',
    'jad357' =>	'/people/jonathan-davis',
    'jb729' =>	'/people/john-barry',
    'wfh2' =>	'/people/william-henning',
    'lew225' =>	'/people/laurie-wilkinson',
    'tsr33' =>	'/people/thomas-rushmer',
    'md855' =>	'/people/mathieu-dupuis',
    'mdc282' =>	'/people/melissa-colbeth',
    'cab552' =>	'/people/caitlyn-bukaty',
    'dlt93' =>	'/people/diana-tyson',
    'ek584' =>	'/people/elizabeth-kucharek',
    'js3358' =>	'/people/jessica-santos',
    'ar2249' =>	'/people/anna-rivera',
    'kml297' =>	'/people/kara-lombardi',
    'ls844' =>	'/people/lisa-schulte',
    'dr563' =>	'/people/dania-rajendra',
    'hb439' =>	'/people/hugh-baran',
    'az438' =>	'/people/alice-zahn',
    'clb362' =>	'/people/cheryl-benk',
    'sd786' =>	'/people/sonila-danaj',
    'gc464' =>	'/people/genevieve-coderre-lapalme',
    'kf322' =>	'/people/kurt-fritjofson',
    'jjs28' =>	'/people/jerusha-saldana',
    'kl852' =>	'/people/kennys-lawson',
    'mci9' =>	'/people/michael-iadevaia',
    'bld57' =>	'/people/bryan-dominguez',
    'raw329' =>	'/people/ryan-wentz',
    'jy26' =>	'/people/jeongkoo-yoon',
    'srt8' =>	'/people/shane-thye',
    'ags253' =>	'/people/amy-saz',
    'pn233' =>	'/people/pilar-nunez-cortes-contreras',
    'ub36' =>	'/people/utku-baris-balaban',
    'dc525' =>	'/people/dawn-cornell',
    'jc3267' =>	'/people/jaime-cabeza',
    'mmb342' =>	'/people/melissa-burgess',
    'sea88' =>	'/people/sarah-anderson',
    'kc948' =>	'/people/kimberly-cook',
    'ss2658' =>	'/people/stephen-salerno',
    'mr2235' =>	'/people/margaret-reed',
    'emd246' =>	'/people/evellyn-demitchell-rodriguez',
    'ad664' =>	'/people/alan-dye',
    'ebc59' =>	'/people/emma-costa',
    'cwm79' =>	'/people/charles-morris',
    'am2867' =>	'/people/aurelia-lorena-murga',
    'clc335' =>	'/people/christopher-candelaria',
    'eep63' =>	'/people/elizabeth-parker',
    'mes467' =>	'/people/mary-shelato',
    'gl435' =>	'/people/goeun-lee',
    'cth49' =>	'/people/colton-haney',
    'cwg22' =>	'/people/chad-gray',
    'rss347' =>	'/people/rebecca-studin',
    'hbw5' =>	'/people/harlan-work',
    'fk92' =>	'/people/frank-kleemann',
    'lt265' =>	'/people/lynne-turner',
    'dd469' =>	'/people/darlene-dubuisson',
    'mp658' =>	'/people/myung-joon-park',
    'ac935' =>	'/people/alicia-canas',
    'km557' =>	'/people/katie-mcbride',
    'jh973' =>	'/people/john-hind',
    'ab2234' =>	'/people/alyssa-bonilla',
    'ms2572' =>	'/people/ming-hao-shiao',
    'ca333' =>	'/people/christin-avgar',
    'rdb269' =>	'/people/roger-bybee',
    'es775' =>	'/people/esra-sarioglu',
    'ak839' =>	'/people/adene-karhan',
    'rjp275' =>	'/people/randy-parker',
    'dca58' =>	'/people/desiree-alexander',
    'eah28' =>	'/people/e-angela-herrera',
    'rb655' =>	'/people/balu-balasubramaniam',
    'gn87' =>	'/people/gregory-nolan',
    'rf433' =>	'/people/reid-friedson',
    'sl2857' =>	'/people/shuli-levine',
    'nk396' =>	'/people/na-yoon-kim',
    'al2357' =>	'/people/andre-lepine',
    'mc2597' =>	'/people/matthew-chase',
    'mc997' =>	'/people/matthew-chase-0',
    'mf244' =>	'/people/melissa-manning',
    'mew64' =>	'/people/marcia-waffner',
    'ear245' =>	'/people/erika-rose',
    'lf323' =>	'/people/louise-floyd',
    'ln268' =>	'/people/laura-nase',
    'vcb6' =>	'/people/valerie-benjamin',
    'ia258' =>	'/people/ifeoma-ajunwa',
    'eg583' =>	'/people/elizabeth-gipson',
    'nc522' =>	'/people/ngan-collins',
    'wq45' =>	'/people/wendy-quarles',
    'ta363' =>	'/people/tom-addonizio',
    'ms3455' =>	'/people/michelle-sawyer',
    'lm773' =>	'/people/lauren-merriman',
    'sm2694' =>	'/people/sarah-murray',
    'ah984' =>	'/people/anna-hamill',
    'vb323' =>	'/people/valentin-bouchet',
    'elg234' =>	'/people/erica-groshen',
    'mks226' =>	'/people/maria-gratias-sinon',
    'jms733' =>	'/people/jessica-stewart',
    'lrs237' =>	'/people/latisha-slowe-mccloud',
    'dp597' =>	'/people/devon-proudfoot',
    'er488' =>	'/people/evan-riehl',
    'bl679' =>	'/people/brian-lucas',
    'jes587' =>	'/people/jennifer-shipe',
    'ao392' =>	'/people/alina-oconnor',
    'jn497' =>	'/people/jason-newton',
    'whr56' =>	'/people/willa-royce-roll',
    'pls8' =>	'/people/pamela-stepp',
    'ks844' =>	'/people/kristin-szczepaniec',
    'vsa9' =>	'/people/virginia-atkinson',
    'kk942' =>	'/people/kwanghyun-kim',
    'zo35' =>	'/people/zachary-olsen',
    'hx63' =>	'/people/handan-xu',
    'rm956' =>	'/people/ryan-marx',
    'cf464' =>	'/people/carlotta-favretto',
    'nk648' =>	'/people/nelson-kezoh',
    'blp27' =>	'/people/brenda-bennett',
    'dc928' =>	'/people/david-collings',
    'tm628' =>	'/people/timothy-mcnutt',
    'jrm567' =>	'/people/jennifer-merchant',
    'rp628' =>	'/people/ricardo-pereira',
    'nc525' =>	'/people/nellie-chu',
    'kcp48' =>	'/people/kevin-packard',
    'jl3783' =>	'/people/juan-liao',
    'da483' =>	'/people/dario-azzellini',
    'co27' =>	'/people/carolina-osorio-gil',
    'naz27' =>	'/people/napoleon-zapata',
    'jj653' =>	'/people/jo-ann-jonker-banfield',
    'is423' =>	'/people/inbal-shlosberg-sela',
    'ko259' =>	'/people/kimberly-osmani',
    'cmw32' =>	'/people/christine-wlosinski',
    'fbk2' =>	'/people/fred-kotler',
    'cam477' =>	'/people/christopher-mcginn',
    'rj272' =>	'/people/rachel-joseph',
    'mtb242' =>	'/people/marc-brudzinski',
    'sc2383' =>	'/people/sarah-chowdhury',
    'jbs392' =>	'/people/john-schamel',
    'sc2895' =>	'/people/sarah-chowdhury-0',
    'rjc374' =>	'/people/rachel-colman',
    'mf634' =>	'/people/matthew-fusco',
    'ww94' =>	'/people/william-wong',
    'ls699' =>	'/people/leslie-shaw',
    'eam363' =>	'/people/emily-morlock',
    'kf264' =>	'/people/kyle-friend',
    'lp467' =>	'/people/laura-perez',
    'yh579' =>	'/people/yuling-hao',
    'kac383' =>	'/people/kelly-clark',
    'jm2229' =>	'/people/jimmy-moreira',
    'sc2899' =>	'/people/steven-calco',
    'ar2336' =>	'/people/antonio-rodrigues-de-freitas-junior',
    'bw485' =>	'/people/betsy-wiggers',
    'kk956' =>	'/people/kyung-kim',
    'cjl2' =>	'/people/carl-lagoze',
    'lmh275' =>	'/people/lauren-haas',
    'njg56' =>	'/people/nicole-golias',
    'yv24' =>	'/people/yoav-vardi',
    'ams834' =>	'/people/alan-sharafi',
    'ebe33' =>	'/people/emily-ellis',
    'jm2664' =>	'/people/jessie-mancilla',
    'so387' =>	'/people/sean-obrady',
    'jg2285' =>	'/people/jerome-gautie',
    'mnp38' =>	'/people/mariko-payton',
    'eb724' =>	'/people/elodie-bethoux',
    'kmm493' =>	'/people/kathleen-mulligan',
    'slo38' =>	'/people/stephanie-olszewski',
    'es964' =>	'/people/eric-sandoval-hernandez',
    'hmr47' =>	'/people/haley-rowland',
    'wt288' =>	'/people/william-talmadge',
    'pjl235' =>	'/people/patrick-luker',
    'fm423' =>	'/people/fabiola-mieres',
    'mg2366' =>	'/people/marcin-graban',
    'dat228' =>	'/people/david-ticzon',
    'aq33' =>	'/people/alexander-quinter',
    'vpv5' =>	'/people/vivian-vazquez',
    'scg89' =>	'/people/sararose-gaines',
    'vsn9' =>	'/people/vendela-norman',
    'jft65' =>	'/people/johanna-tuttle',
    'kay28' =>	'/people/kayleigh-yerdon',
    'rb777' =>	'/people/retina-bethea',
    'mja276' =>	'/people/madeleine-allan-rahill',
    'cs2396' =>	'/people/carmen-saez',
    'rh24' =>	'/people/rehana-huq',
    'ab744' =>	'/people/alexis-boytsov',
    'yw447' =>	'/people/yudi-wang',
    'smj235' =>	'/people/sophonie-joseph',
    'acb346' =>	'/people/august-biben',
    'afl38' =>	'/people/ana-lopezulloa',
    'jch393' =>	'/people/joseph-hall',
    'lz538' =>	'/people/lizhen-zheng',
    'wc634' =>	'/people/won-seok-choi',
    'rss375' =>	'/people/richard-stafford',
    'sjb372' =>	'/people/sandra-berkowitz-stafford',
    'zb83' =>	'/people/zachariah-beauvais',
    'tk643' =>	'/people/tara-kastenhuber',
    'ls927' =>	'/people/lloyd-sachikonye',
    'cg642' =>	'/people/casper-gelderblom',
    'hh672' =>	'/people/hua-haifeng',
    'pem78' =>	'/people/paula-maguire',
    'mph222' =>	'/people/matthew-hobbs',
    'reb369' =>	'/people/rose-blinn',
    'jr2284' =>	'/people/johanna-richter',
    'td367' =>	'/people/tracy-dumas',
    'eap252' =>	'/people/estefania-palacios',
    'ajd295' =>	'/people/albert-daghita',
    'jrm375' =>	'/people/john-mueller',
    'ems434' =>	'/people/elizabeth-student',
    'jc3435' =>	'/people/john-castella',
    'ab968' =>	'/people/aaron-bartley',
    'kb482' =>	'/people/kathleen-brody',
    'vrp5' =>	'/people/victoria-parker',
    'jb2638' =>	'/people/jin-sun-bae',
    'hm376' =>	'/people/hunter-moskowitz',
    'jra222' =>	'/people/jeff-amaral',
    'dcr97' =>	'/people/dara-riegel',
    'mfl55' =>	'/people/michael-lovenheim',
    'cj299' =>	'/people/christina-jean-louis',
    'jd795' =>	'/people/jose-de-resende-junior',
    'kls56' =>	'/people/kathryn-hamilton',
    'tbe3' =>	'/people/tonya-engst',
    'hra27' =>	'/people/heather-lacombe',
    'jjc369' =>	'/people/jeffrey-crosby',
    'wjc95' =>	'/people/wesley-chenault',
    'sds222' =>	'/people/steven-spencer',
    'bj272' =>	'/people/brandon-johnson',
    'reh279' =>	'/people/rachel-harkins',
    'nnl9' =>	'/people/neva-low',
    'eg548' =>	'/people/ellen-gallin-procida',
    'dvb38' =>	'/people/denise-brown-hart',
    'sp2458' =>	'/people/sanjay-pinto',
    'mgp38' =>	'/people/mark-pearce',
    'msf252' =>	'/people/marcie-farwell',
    'fa289' =>	'/people/fnu-adnan',
    'pg466' =>	'/people/philipp-geiler',
    'jg2227' =>	'/people/james-alaindy-gourdet',
    'ctf8' =>	'/people/carly-hills',
    'jlj68' =>	'/people/james-jackson',
    'as947' =>	'/people/arianna-schindle',
    'sk3244' =>	'/people/steven-kaczmarczyk',
    'mr2295' =>	'/people/maria-rider',
    'ak2589' =>	'/people/andrew-karhan',
    'cr572' =>	'/people/celene-reynolds',
    'mfl64' =>	'/people/micaela-lipman',
    'jag235' =>	'/people/julie-greco',
    'je349' =>	'/people/jessica-ellott',
    'cjh325' =>	'/people/cameron-hass',
    'jc2596' =>	'/people/jaylexia-clark',
    'hc833' =>	'/people/hannah-cho',
    'iak32' =>	'/people/angelica-keen',
    'ps983' =>	'/people/peter-smith',
    'no232' =>	'/people/nicholas-occhiuto',
    'bb679' =>	'/people/bryan-bates',
    'db833' =>	'/people/dina-bishara',
    'ti92' =>	'/people/tristan-ivory',
    'rar33' =>	'/people/rebecca-kehoe',
    'al2488' =>	'/people/alice-lee',
    'ss3977' =>	'/people/seth-sanders',
    'jds535' =>	'/people/jon-stern',
    'cr487' =>	'/people/chloe-rippe',
    'jl3984' =>	'/people/johanna-lugo',
    'jf843' =>	'/people/jeneen-fraser',
    'zd225' =>	'/people/zachary-driscoll',
    'dwb267' =>	'/people/daniel-berman',
    'rks237' =>	'/people/ruth-silcoff',
    'jgs293' =>	'/people/jacob-silcoff',
    'll889' =>	'/people/lilach-lurie',
    'rl848' =>	'/people/regina-lenz',
    'cp646' =>	'/people/cathy-pantelides',
    'rw597' =>	'/people/russell-weaver',
    'tp348' =>	'/people/tae-youn-park',
    'ej252' =>	'/people/elizabeth-juaniza-saso',
    'mlc44' =>	'/people/michelle-cole',
    'jlr343' =>	'/people/jennifer-rudolph',
    'mws269' =>	'/people/manuel-santana',
    'cy497' =>	'/people/charles-yee',
    'bdd28' =>	'/people/brian-dunn',
    'dx68' =>	'/people/xuewei-dou',
    'hl2378' =>	'/people/huijie-li',
    'mc2434' =>	'/people/sean-chang',
    'cgd48' =>	'/people/camille-dupuis',
    'ely22' =>	'/people/elias-young',
    'rm2236' =>	'/people/rhonda-mccelland',
    'sdm39' =>	'/people/samuel-magavern',
    'kk667' =>	'/people/kyoungyoung-kim',
    'ank34' =>	'/people/anna-kreisberg',
    'wbk39' =>	'/people/whitney-kramer',
    'cm687' =>	'/people/christine-molloy',
    'mw684' =>	'/people/mary-ann-white',
    'jb2329' =>	'/people/jason-bryer',
    'sap43' =>	'/people/samantha-strickland',
    'maw384' =>	'/people/michelle-warfield',
    'ab2245' =>	'/people/angela-bast',
    'clh278' =>	'/people/christine-hess',
    'pt296' =>	'/people/pericles-tima',
    'jg873' =>	'/people/jhims-gerard',
    'sh923' =>	'/people/santoshi-halder',
    'dt496' =>	'/people/daphney-themistocle',
    'tb449' =>	'/people/tony-byers',
    'arb396' =>	'/people/andrew-battle',
    'yg443' =>	'/people/yanan-guo',
    'xl825' =>	'/people/xiaotian-li',
    'nwl28' =>	'/people/wing-kay-nicole-lee',
    'sb2623' =>	'/people/saskia-bouman',
    'jj729' =>	'/people/jason-judd',
    'bb686' =>	'/people/brittany-bond',
    'sb2626' =>	'/people/sarah-besky',
    'sf562' =>	'/people/sean-fath',
    'pk532' =>	'/people/philipp-kircher',
    'dg698' =>	'/people/desiree-leclercq',
    'cm848' =>	'/people/courtney-mccluney',
    'tn329' =>	'/people/tejasvi-nagaraja',
    'dy338' =>	'/people/duanyi-yang',
    'sm2637' =>	'/people/sandra-sofia-mosqueira-caminada',
    'kb726' =>	'/people/katie-brendli',
    'mg2433' =>	'/people/megan-garton',
    'am3356' =>	'/people/amber-mcconnell',
    'roh29' =>	'/people/richard-hartman',
    'bc664' =>	'/people/biviana-coyomani',
    'mb2693' =>	'/people/michele-belot',
    'ao269' =>	'/people/andrea-ó-súilleabháin',
    'ssb237' =>	'/people/sarah-baker',
    'jg847' =>	'/people/jonathan-gresham',
    'jkr68' =>	'/people/jennifer-royer',
    'wlg37' =>	'/people/wendy-guild-swearingen',
    'bcg54' =>	'/people/barbara-gillen',
    'cac275' =>	'/people/cathy-creighton',
    'zc262' =>	'/people/zach-cunningham',
    'ah679' =>	'/people/avalon-hoek-spaans',
    'ar724' =>	'/people/anita-raman',
    'jg922' =>	'/people/june-gothberg',
    'mmg234' =>	'/people/michelle-goldberg',
    'hlk59' =>	'/people/harry-kosowsky',
    'sc2537' =>	'/people/shiya-cao',
    'lr446' =>	'/people/leah-rosner',
    'smb253' =>	'/people/susan-beauregard',
    'esc93' =>	'/people/lizzie-cushing',
    'lmd255' =>	'/people/lydia-dempsey',
    'sr876' =>	'/people/shira-reisman',
    'js2898' =>	'/people/johanna-schenner',
    'ysw7' =>	'/people/y-samuel-wang',
    'kz63' =>	'/people/katerina-zhuravel',
    'lc759' =>	'/people/lucas-clover-alcolea',
    'ah768' =>	'/people/aibak-hafeez',
    'kmh234' =>	'/people/kayla-hoffman',
    'fh246' =>	'/people/franciscus-hijkoop',
    'lmd228' =>	'/people/lillien-ellis',
    'lv89' =>	'/people/leah-vosko',
    'gk346' =>	'/people/gerald-kernerman',
    'jo354' =>	'/people/joseph-oconnor',
    'efw46' =>	'/people/eliza-wildes',
    'ars382' =>	'/people/angela-shurtleff',
    'jq89' =>	'/people/junyue-qian',
    'vp253' =>	'/people/varvara-palli',
    'eac262' =>	'/people/emily-cotman',
    'jb2362' =>	'/people/jennifer-brooks',
    'tr295' =>	'/people/tamara-robinson',
    'cr586' =>	'/people/callan-robinson',
    'gm464' =>	'/people/gabriele-macci',
    'xw582' =>	'/people/xiaoxian-wang',
    'ss3258' =>	'/people/sabrina-sornberger',
    'as2989' =>	'/people/andres-stagnaro',
    'seb349' =>	'/people/sara-brooks',
    'vc337' =>	'/people/vicki-chang',
    'klm27' =>	'/people/kathy-mix',
    'ab2532' =>	'/people/anne-marie-brady',
    'dw529' =>	'/people/debora-wagner',
    'ds954' =>	'/people/donna-spotton',
    'ek583' =>	'/people/elaine-kim',
    'swm9' =>	'/people/scott-marshall',
    'xz747' =>	'/people/xiji-zhu',
    'scv27' =>	'/people/sharon-vitello',
    'af449' =>	'/people/armando-flores',
    'xy374' =>	'/people/dana-yang',
    'cb882' =>	'/people/coert-bonthius',
    'sfb9' =>	'/people/stephen-babbles',
    'yp244' =>	'/people/yasir-parvez',
    'db669' =>	'/people/danielle-riley',
    'crs339' =>	'/people/christine-schmidt',
    'mh597' =>	'/people/michel-hermans',
    'aa2232' =>	'/people/atak-ayaz',
    'mi14' =>	'/people/matthew-ibeike-jonah',
    'rl675' =>	'/people/robyn-leary',
    'yt486' =>	'/people/yuhsiang-tse',
    'mn497' =>	'/people/maclain-naumann',
    'hk587' =>	'/people/hannah-kim',
    'lm755' =>	'/people/lawrence-mancuso',
    'kj256' =>	'/people/kaitlyn-jackson',
    'jdc77' =>	'/people/jess-cisco',
    'baw242' =>	'/people/ben-wrubel',
    'ab2574' =>	'/people/ashley-bryant',
    'ztb22' =>	'/people/zachary-ballance',
    'jat358' =>	'/people/jessica-tobias',
    'sb74' =>	'/people/sabel-bong',
    'nan49' =>	'/people/nastasia-nikitina',
    'jcf287' =>	'/people/joshua-felver',
    'jrl364' =>	'/people/jonathan-long',
    'pl82' =>	'/people/penny-lane-spoonhower',
    'jd866' =>	'/people/joy-das',
    'ad838' =>	'/people/amanda-delee',
    'kal299' =>	'/people/katherine-lewis',
    'vay2' =>	'/people/victor-yengle',
    'hc285' =>	'/people/heather-cobb',
    'se333' =>	'/people/sofia-encarnacion',
    'aab238' =>	'/people/allie-ambriz',
    'sam22' =>	'/people/sarah-espinosa',
    'ak2223' =>	'/people/alexander-kowalski',
    'jm2749' =>	'/people/jennifer-migliore',
    'go67' =>	'/people/gina-oswald',
    'gr375' =>	'/people/gali-racabi',
    'cr589' =>	'/people/caitlin-ray',
    'ct633' =>	'/people/cheryl-teare',
    'yz2829' =>	'/people/yiran-zhang',
    'reg252' =>	'/people/riley-griffin',
    'ram498' =>	'/people/robin-mcnabb',
    'ndl43' =>	'/people/nathan-lamm',
    'jwn54' =>	'/people/jessica-nelson',
    'dc795' =>	'/people/daniel-castro',
    'rr677' =>	'/people/rachel-raymond',
    'dl852' =>	'/people/danning-li',
    'ts735' =>	'/people/tilde-siglev',
    'mzc6' =>	'/people/michelle-chen',
    'mm2886' =>	'/people/marie-catherine-mignault',
    'cw927' =>	'/people/charnan-williams',
    'dak275' =>	'/people/debbie-krahmer',
    'bh582' =>	'/people/ben-harper',
    'ed466' =>	'/people/liz-davis-frost',
    'map457' =>	'/people/marissa-porter',
    'mja286' =>	'/people/melissa-allen',
    'jre4' =>	'/people/joshua-eckenrode',
    'pcf45' =>	'/people/paul-fama',
    'ams758' =>	'/people/aaron-stapleton',
    'bkd34' =>	'/people/bart-de-koning',
    'lrh83' =>	'/people/l-rebecca-hann',
    'mgo5' =>	'/people/mary-opperman',
    'jlh455' =>	'/people/jessica-burnette',
    'prr39' =>	'/people/pura-rodriguez',
    'imb44' =>	'/people/isadora-bratton-benfield',
    'jap477' =>	'/people/jennifer-pawlewicz',
    'zjw25' =>	'/people/zoë-west',
    'db873' =>	'/people/debbie-beausejour',
    'ms3578' =>	'/people/melissa-shetler',
    'igp2' =>	'/people/iris-packman',
    'mlo67' =>	'/people/macie-owens',
    'am3283' =>	'/people/alexandra-michael',
    'llm229' =>	'/people/lucy-mcdonald',
    'cv267' =>	'/people/chris-vatland',
    'tb542' =>	'/people/tobias-blay',
    'jmt19' =>	'/people/jean-miller',
    'pjg97' =>	'/people/pam-gueldner',
    'cg686' =>	'/people/cosimo-gaudio',
    'cet55' =>	'/people/caitlin-tabor',
    'hjr27' =>	'/people/heidi-russell',
    'ig266' =>	'/people/isabel-guerrero',
    'jwa85' =>	'/people/jodi-anderson',
    'dau2' =>	'/people/david-unger',
    'pk522' =>	'/people/peifeng-lin-kevin-lin',
    'sm2842' =>	'/people/sneha-mishra',
    'awf47' =>	'/people/alexander-foley',
    'ss3545' =>	'/people/sanbai-sun',
    'eaj74' =>	'/people/emily-jensen',
    'hj389' =>	'/people/heeeun-jang',
    'co283' =>	'/people/cecilia-oyediran',
    'km944' =>	'/people/kamila-moulai',
    'rsc265' =>	'/people/reyna-cohen',
    'ahd28' =>	'/people/alan-davidoff',
    'tkk25' =>	'/people/taylor-kohnstam',
    'ebr25' =>	'/people/elisa-rafferty',
    'sd445' =>	'/people/susanne-donovan',
    'pjm344' =>	'/people/patrick-mehler',
    'sp2272' =>	'/people/steve-peraza',
    'dm899' =>	'/people/daniel-molczyk',
    'mf834' =>	'/people/mario-fernando',
    'jnh87' =>	'/people/josh-haines',
    'gwa27' =>	'/people/george-adanuty',
    'jhu7' =>	'/people/jasmine-umrigar',
    'lp397' =>	'/people/lindarose-piccolo',
    'ml2722' =>	'/people/melissa-louidor',
    'js3737' =>	'/people/jacob-silvershein',
    'ag2539' =>	'/people/alec-goodwin',
    'jje68' =>	'/people/johnnie-english',
    'nsn3' =>	'/people/natalia-navas',
    'abw72' =>	'/people/andrew-wolf',
    'jtc277' =>	'/people/james-carter',
    'sja89' =>	'/people/santiago-anria',
    'jb2722' =>	'/people/justin-bloesch',
    'tm683' =>	'/people/tiffany-miller',
    'eab366' =>	'/people/adelle-blackett',
    'lk372' =>	'/people/lauren-kessler',
    'jam835' =>	'/people/jillian-morley',
    'bjd96' =>	'/people/benjamin-drew',
    'tnh34' =>	'/people/tomisha-hicks',
    'rp656' =>	'/people/rohan-palacios',
    'jmg552' =>	'/people/jack-gilson',
    'rsc267' =>	'/people/randall-claar',
    'ae373' =>	'/people/ahmed-el-sammak',
    'kmc356' =>	'/people/ketchel-carey',
    'sa848' =>	'/people/salima-ali',
    'js3677' =>	'/people/jieying-shen',
    'dkp48' =>	'/people/david-peterson',
    'cp678' =>	'/people/chansol-park',
    'nn332' =>	'/people/nam-nguyen',
    'nd438' =>	'/people/nhat-phi-dao',
    'ktt35' =>	'/people/kathryn-taylor',
    'hkm38' =>	'/people/harleigh-myerovich',
    'vm348' =>	'/people/vincenzo-maccarrone',
    'sd895' =>	'/people/seph-daradar',
    'mld256' =>	'/people/michael-davis',
    'apj46' =>	'/people/allen-jackson',
    'dv288' =>	'/people/diana-velia-villa',
    'kms488' =>	'/people/katherine-simpson',
    'sc3343' =>	'/people/seung-hun-chung',
    'aks287' =>	'/people/lexi-scanlon',
    'lea24' =>	'/people/linda-amuso',
    'dss355' =>	'/people/devika-shekhawat',
    'ak2593' =>	'/people/amit-kramer',
    'kk2226' =>	'/people/karen-kramer',
    'dhw75' =>	'/people/douglas-wigdor',
    'll983' =>	'/people/lejdina-lluga',
    'ct674' =>	'/people/christina-trinh',
    'dt542' =>	'/people/dominic-tribelli',
    'mja295' =>	'/people/maheya-afnan',
    'ahd74' =>	'/people/anne-dececco',
    'kip3' =>	'/people/kelly-pike',
    'pj275' =>	'/people/pauline-jerrentrup',
    'hmm99' =>	'/people/holly-manaseri',
    'at948' =>	'/people/aleyda-toruno',
    'sh2632' =>	'/people/steven-hickey',
    'gj232' =>	'/people/garphil-julien',
    'al2567' =>	'/people/angie-liao',
    'adr223' =>	'/people/alejandra-rodriguez-diaz',
    'jps442' =>	'/people/jim-sierotnik',
    'kcc86' =>	'/people/kyra-coleman',
    'eth53' =>	'/people/erwin-heffron',
    'dbr38' =>	'/people/david-ritter',
    'mt749' =>	'/people/megan-thorsfeldt',
    'kek264' =>	'/people/kathryn-keegan',
    'bs739' =>	'/people/brett-sherman',
    'jla297' =>	'/people/jason-arroyo',
    'ejf235' =>	'/people/bethany-figueroa',
    'spl49' =>	'/people/samuel-lavine',
    'gcm63' =>	'/people/gianna-marotta',
    'dcs323' =>	'/people/dylan-smith',
    'pdm99' =>	'/people/pria-mahadevan',
    'cwd62' =>	'/people/claire-deng',
    'mlh77' =>	'/people/michael-huyghue',
    'jah566' =>	'/people/jennifer-best',
    'mc2883' =>	'/people/michelle-corbeaux',
    'scb274' =>	'/people/spencer-beswick',
    'mcg99' =>	'/people/candelaria-garay',
    'yk762' =>	'/people/youbin-kang',
    'mah477' =>	'/people/mira-harris',
    'emv39' =>	'/people/bella-vandenberg',
    'blc97' =>	'/people/brianne-crowley',
    'jb2758' =>	'/people/jaz-brisack',
    'gc566' =>	'/people/gerald-cotiangco',
    'mgl88' =>	'/people/michael-lenmark',
    'cfj32' =>	'/people/charles-johnson',
    'hak78' =>	'/people/hadia-khan',
    'ymd6' =>	'/people/yohanes-duli',
    'cg724' =>	'/people/costanza-galanti',
    'es2267' =>	'/people/eunkyoung-shin',
    'sah376' =>	'/people/sonia-harney',
    'am3276' =>	'/people/adam-mrozowicki',
    'jb2767' =>	'/people/jacek-burski',
    'keh244' =>	'/people/katie-hayden',
    'rae96' =>	'/people/raymond-everett',
    'bat49' =>	'/people/beth-taylor',
    'br453' =>	'/people/bjarke-refslund',
    'rb786' =>	'/people/rebecca-baines',
    'ad2384' =>	'/people/anthony-dodd',
    'cjm429' =>	'/people/colin-montgomery',
    'sr2384' =>	'/people/samira-rafaela',
    'fah47' =>	'/people/faith-hahn',
    'av587' =>	'/people/alex-veen',
    'dw584' =>	'/people/desai-wang',
    'an97' =>	'/people/amy-newman',
    'ry58' =>	'/people/ryan-yeh',
    'ams699' =>	'/people/amalia-schneider',
    'jrw358' =>	'/people/jennifer-weiss',
    'mjt273' =>	'/people/margot-treadwell',
    'ah2383' =>	'/people/andrew-hume',
    'lb835' =>	'/people/lisa-boragine',
    'as4352' =>	'/people/andrew-shea',
    'mb2865' =>	'/people/maximillian-breiling',
    'pak229' =>	'/people/peri-kirkpatrick',
    'bcv26' =>	'/people/benjamin-velasquez',
    'ejr254' =>	'/people/everett-rutan',
    'nen37' =>	'/people/nguengeti-ngwen',
    'cbg64' =>	'/people/cayman-giordano',
    'pmc236' =>	'/people/pauline-carry',
    'ert47' =>	'/people/emma-teitelman',
    'fsb42' =>	'/people/forrest-briscoe',
    'en322' =>	'/people/elio-nimier-david',
    'pao35' =>	'/people/paul-ortiz',
    'jas2243' =>	'/people/jason-sockin',
    'dsw245' =>	'/people/devin-wiggs',
    'trm76' =>	'/people/thomas-morgan',
    'jz2326' =>	'/people/jian-zou',
    'cls386' =>	'/people/christina-stover',
    'tk698' =>	'/people/trynia-kaufman',
    'bpw46' =>	'/people/brian-wakamo',
    'jo432' =>	'/people/jonelle-orsaio',
    'isn8' =>	'/people/ilanith-nizard',
    'brl62' =>	'/people/brian-lu',
    'eh693' =>	'/people/eunice-han',
    'cht45' =>	'/people/charlie-townsend',
    'ul33' =>	'/people/usman-liaquat',
    'hr382' =>	'/people/hassan-ragy',
    'ln293' =>	'/people/lynda-nguyen',
    'yl3945' =>	'/people/yichen-liu',
    'ajg322' =>	'/people/alexander-gimenez',
    'sab496' =>	'/people/sarah-bubash',
    'lag297' =>	'/people/lyndsay-gehring',
    'sms733' =>	'/people/stephanie-spicciati',
    'ae58' =>	'/people/anne-ensminger',
    'sc166' =>	'/people/sarah-castle',
    'zwc4' =>	'/people/zachary-cunningham',
    'jk2854' =>	'/people/joonyoung-kim',
    'mjr448' =>	'/people/malte-rattenborg',
    'njg78' =>	'/people/neil-goldsmith',
    'asl96' =>	'/people/amy-li',
    'gjm244' =>	'/people/grennan-milliken',
    'tjb294' =>	'/people/tyler-bailey',
  ];

  $entity_type_manager = \Drupal::service('entity_type.manager');
  $alias_manager = \Drupal::service('path_alias.manager');
  $persona_storage = $entity_type_manager->getStorage('persona');
  $persona_query = $persona_storage->getQuery();
  $persona_query->accessCheck(FALSE);
  $persona_query->condition('type', 'ilr_employee');
  $persona_query->condition('field_netid', array_keys($d7_aliases), 'IN');
  $persona_results = $persona_query->execute();
  $personas = $persona_storage->loadMultiple($persona_results);

  foreach ($personas as $persona) {
    $alias = $alias_manager->getAliasByPath($persona->toURL()->toString());

    if (!in_array($alias, $d7_aliases)) {
      $redirect = $entity_type_manager->getStorage('redirect')->create([
        'redirect_source' => $d7_aliases[$persona->field_netid->value],
        'redirect_redirect' => 'internal:/persona/' . $persona->id(),
        'status_code' => 301,
      ]);

      $redirect->save();
    }
  }
}
