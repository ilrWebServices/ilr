<?php

/**
 * @file
 * Post update functions for the ILR module.
 */

/**
 * Add alt attributes for instructor photos that don't have one.
 */
function ilr_post_update_instructor_photo_alt_attributes(&$sandbox) {
  $modified_media_count = 0;

  // Get all instructor nodes.
  $instructor_nids = \Drupal::entityQuery('node')->condition('type','instructor')->execute();
  $instructor_nodes = \Drupal\node\Entity\Node::loadMultiple($instructor_nids);

  foreach ($instructor_nodes as $instructor_node) {
    // Get the media entity for the instructor photo.
    $instructor_image_media_entities = $instructor_node->field_representative_image->referencedEntities();

    if (!$instructor_image_media_entities) {
      continue;
    }

    foreach ($instructor_image_media_entities as $instructor_image_media_entity) {
      $image = $instructor_image_media_entity->field_media_image;

      // If the alt attribute is empty, set it to the instructor node label
      // (i.e. the instructor's name).
      if (!$image->alt) {
        $image->alt = 'Photo of ' . $instructor_node->label();
        $instructor_image_media_entity->save();
        $modified_media_count++;
      }
    }
  }

  return t('%modified_media_count instructor photo media entities were updated with new alt attributes.', [
    '%modified_media_count' => $modified_media_count
  ]);
}

/**
 * Add add a course message block with a given UUID for layout builder.
 */
function ilr_post_update_course_message_block(&$sandbox) {
  $blockEntityManager = \Drupal::service('entity_type.manager')->getStorage('block_content');

  $block = $blockEntityManager->create([
    'type' => 'simple_text',
    'uuid' => '280c1d2d-0456-45eb-84dc-d114c5e7b2fa',
    'info' => 'Course Message',
    'label_display' => 0,
  ]);

  $block->body->value = '<p>Effective March 16, 2020, the educational programs scheduled to be delivered at our New York City facility will be moved to virtual delivery, where possible, or rescheduled. For those programs that do not lend themselves to a distance-education format, we will postpone them until we receive guidance from the University and public health officials that it is safe to hold those programs in-person. Please contact <a href="mailto:ilrcustomerservice@cornell.edu">ilrcustomerservice@cornell.edu</a> with any questions. We will provide updates as they become available from the University.</p>';
  $block->body->format = 'basic_formatting';

  $block->save();
}

/**
 * Add a message block for courses without classes.
 */
function ilr_post_update_course_without_classes_message_block(&$sandbox) {
  $blockEntityManager = \Drupal::service('entity_type.manager')->getStorage('block_content');

  $block = $blockEntityManager->create([
    'type' => 'simple_text',
    'uuid' => 'ef42c069-7cd4-4906-9f67-f56ef8d5d236',
    'info' => 'Message: course without classes',
    'label_display' => 0,
  ]);

  $block->body->value = '<div class="message__content">
<p>Dates for online sessions will be posted as soon as they are available.<br />
Sign up to be notified when new dates are available.</p>
</div>
<p><span class="cu-button cu-button--alt in-page-signup-jump">Get notified</span></p>
';
  $block->body->format = 'full_html';
  $block->save();
}

/**
 * Move rich text paragraph heading field values into the formatted text.
 */
function ilr_post_update_rich_text_headings(&$sandbox) {
  // Get all rich text paragraphs with heading or subheading values.
  $query = \Drupal::entityQuery('paragraph');
  $group = $query->orConditionGroup()
    ->condition('field_heading', NULL, 'IS NOT NULL')
    ->condition('field_subheading', NULL, 'IS NOT NULL');
  $query->condition('type', 'rich_text')->condition($group);
  $rich_text_paragraph_ids = $query->execute();

  $rich_text_paragraphs = \Drupal\paragraphs\Entity\Paragraph::loadMultiple($rich_text_paragraph_ids);

  // Prepend the headings to the body field as h2 and h3 elements.
  foreach ($rich_text_paragraphs as $rich_text_paragraph) {
    $body = $rich_text_paragraph->field_body->value;

    if (!$rich_text_paragraph->field_subheading->isEmpty()) {
      $body = '<h3>' . $rich_text_paragraph->field_subheading->value . '</h3>' . PHP_EOL . $body;
    }

    if (!$rich_text_paragraph->field_heading->isEmpty()) {
      $body = '<h2>' . $rich_text_paragraph->field_heading->value . '</h2>' . PHP_EOL . $body;
    }

    $rich_text_paragraph->field_body->value = $body;
    $rich_text_paragraph->save();
  }
}

/**
 * Copy node field_sections tmp data to new legacy field.
 */
function ilr_post_update_node_field_sections_legacy(&$sandbox) {
  $connection = \Drupal::service('database');

  foreach (['node__field_sections_tmp', 'node_revision__field_sections_tmp'] as $table) {
    $query = $connection->select($table)->fields($table);
    $connection->insert(str_replace('tmp', 'legacy', $table))->from($query)->execute();
    $query = $connection->query("DROP TABLE {$table}");
  }
}

/**
 * Add some initial post category terms.
 */
function ilr_post_update_add_post_category_terms(&$sandbox) {
  $term_entity_manager = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term');

  $terms = [
    'Workers',
    'Vulnerable Workers',
    'Employment Policy',
    'Professional Education',
    'Executive Education',
    'COVID',
    'Webinar',
  ];

  foreach ($terms as $term_name) {
    $term_entity_manager->create([
      'vid' => 'post_categories',
      'name' => $term_name,
    ])->save();
  }
}

/**
 * Add initial category vocabulary and terms for covid blog.
 */
function ilr_post_update_add_covid_blog_category_terms(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $collection = $entity_type_manager->getStorage('collection')->load(2);

  // Load the covid vocabulary
  $vocabulary = $entity_type_manager->getStorage('taxonomy_vocabulary')->load('blog_2_categories');

  if ($vocabulary) {
    // Add the vocabulary to the collection.
    $collection_item_vocab = $entity_type_manager->getStorage('collection_item')->create([
      'type' => 'blog',
      'collection' => $collection->id(),
    ]);

    $collection_item_vocab->item = $vocabulary;
    $collection_item_vocab->setAttribute('blog_collection_id', $collection->id());
    $collection_item_vocab->save();

    $term_storage = $entity_type_manager->getStorage('taxonomy_term');

    // Create the child terms.
    $terms = [
      'Online Training',
      'Vulnerable Workers',
      'Workers',
      'Employers',
      'Employment Policy',
      'ILR Voices',
    ];

    foreach ($terms as $term_name) {
      $term_storage->create([
        'vid' => $vocabulary->id(),
        'name' => $term_name,
      ])->save();
    }
  }
}

/**
 * Change `blog_collection_id` attribute key to `blog_taxonomy_categories` with
 * a value of 1 for the vocabulary collection items for the covid and ILR in the
 * News blogs.
 */
function ilr_post_update_fix_blog_category_collection_item_attributes(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $collection_item_storage = $entity_type_manager->getStorage('collection_item');

  // 17 and 23 are the collection_item ids for the blog vocabularies.
  $blog_categories_collection_items = $collection_item_storage->loadMultiple([17, 23]);

  foreach ($blog_categories_collection_items as $collection_item) {
    $collection_item->setAttribute('blog_taxonomy_categories', TRUE);
    $collection_item->removeAttribute('blog_collection_id');
    $collection_item->save();
  }
}

/**
 * Add the `blog_2_tags` and `blog_4_tags` vocabularies to their blog
 * collections with the `blog_taxonomy_tags` attribute set to 1 (TRUE). Also add
 * some default terms to `blog_2_tags`.
 */
function ilr_post_update_add_blog_tag_terms(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $path_alias_manager = \Drupal::service('path.alias_manager');
  $vocabulary_storage = $entity_type_manager->getStorage('taxonomy_vocabulary');
  $collection_storage = $entity_type_manager->getStorage('collection');
  $collection_item_storage = $entity_type_manager->getStorage('collection_item');
  $pathauto_pattern_storage = $entity_type_manager->getStorage('pathauto_pattern');
  $term_storage = $entity_type_manager->getStorage('taxonomy_term');

  foreach ([2, 4] as $collection_id) {
    $collection = $collection_storage->load($collection_id);

    // Load the relevant vocabulary.
    $vocabulary = $entity_type_manager->getStorage('taxonomy_vocabulary')->load('blog_' . $collection_id . '_tags');

    if ($vocabulary) {
      // Add the vocabulary to the collection with the proper attribute so that
      // it can be identified later.
      $collection_item_vocab = $collection_item_storage->create([
        'type' => 'default',
        'collection' => $collection->id(),
      ]);

      $collection_item_vocab->item = $vocabulary;
      $collection_item_vocab->setAttribute('blog_taxonomy_tags', TRUE);
      $collection_item_vocab->save();

      // Add some initial terms to the covid tags vocabulary.
      if ($collection_id === 2) {
        $terms = [
          'Formerly incarcerated',
          'Workers with disabilities',
          'Benefits advisors',
          'Undocumented employees',
          'Gig economy workers',
          'Unions and employers',
          'Unemployment benefits',
          'Webinars',
        ];

        foreach ($terms as $term_name) {
          $term_storage->create([
            'vid' => $vocabulary->id(),
            'name' => $term_name,
          ])->save();
        }
      }
    }
  }
}

/**
 * Fix the node.field_sections schema.
 */
function ilr_post_update_fix_node_field_sections_definition(&$sandbox) {
  $storage_definitions = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions('node');
  // Calling getSchema() also sets it, so the following save() will store it.
  $storage_definitions['field_sections']->getSchema();
  $storage_definitions['field_sections']->save();
}

/**
 * Add initial ilrie publication.
 */
function ilr_post_update_add_ilrie_publication(&$sandbox) {
  $term_entity_manager = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term');

  $term_entity_manager->create([
    'vid' => 'publication',
    'name' => 'ILRie',
  ])->save();
}

/**
 * Update all existing post listings to use the 'grid' list_style setting.
 */
function ilr_post_update_update_post_listing_styles(&$sandbox) {
  $query = \Drupal::entityQuery('paragraph');
  $query->condition('type', 'simple_collection_listing');
  $post_listing_paragraph_ids = $query->execute();
  $simple_post_listings = \Drupal\paragraphs\Entity\Paragraph::loadMultiple($post_listing_paragraph_ids);

  foreach ($simple_post_listings as $simple_post_listing) {
    $settings = $simple_post_listing->getAllBehaviorSettings();
    $settings['post_listing']['list_style'] = 'grid';
    $simple_post_listing->setAllBehaviorSettings($settings);
    $simple_post_listing->save();
  }
}

/**
 * Add the SVG logo to the ILRie publication.
 */
function ilr_post_update_update_ilrie_logo(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $ilrie_term = $entity_type_manager->getStorage('taxonomy_term')->load(51);
  $ilrie_term->field_inline_svg_logo->value = <<<'EOD'
  <svg xmlns:svg="http://www.w3.org/2000/svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 46.85 20.31" id="ilrie">
    <style type="text/css">
      @font-face {
        font-family: 'Replica ILRie';
        font-style: normal;
        font-weight: bold;
        src: url('https://www.ilr.cornell.edu/sites/all/themes/ilr_theme/fonts/lineto-replica-pro-bold.woff2') format('woff2'), url('https://www.ilr.cornell.edu/sites/all/themes/ilr_theme/fonts/lineto-replica-pro-bold.woff') format('woff');
      }
      .title, .subtitle { font-family:Replica,'Replica ILRie',sans-serif; }
      .title { font-size:14.46px; font-weight: bold; }
      .subtitle { font-size:2.5px; font-weight: bold; letter-spacing:-0.01px; }
      .publication-logo--small svg#ilrie .subtitle { display: none; }
      .i { letter-spacing:-0.21px; }
      .l { letter-spacing:-0.32px; }
      .r { letter-spacing:0.42px; }
      .ie { font-size:14.64px; letter-spacing:-0.2px }
    </style>
    <path class="frame" d="M 24.575805,17.926304 H 2.0023907 l 2.555e-4,-2.4e-4 -0.1520305,-0.152364 -0.00241,0.0027 V 1.9942514 L 1.9939758,1.8481952 H 14.005896 L 14.151202,1.7027815 V 0.1418846 L 14.00922,0 H 0.1417128 L 0,0.1416706 V 19.637173 l 0.1371598,0.137286 H 25.104333 l 0.146696,-0.146684 -2.43e-4,-1.555707 -0.145838,-0.145764" />
    <text class="title" y="14.88" x="4.02"><tspan
      class="i">I</tspan><tspan
      class="l">L</tspan><tspan
      class="r">R</tspan><tspan
      class="ie">ie</tspan></text>
    <text class="subtitle" x="27.28" y="19.78">Alumni Magazine</text>
  </svg>
  EOD;
  $ilrie_term->field_inline_svg_logo->format = 'inline_svg';
  $ilrie_term->field_subtitle->value = 'Alumni Magazine';
  $ilrie_term->save();
}

/**
 * Update all existing post listings to use the 'grid' list_style setting.
 */
function ilr_post_update_update_list_styles(&$sandbox) {
  $query = \Drupal::entityQuery('paragraph');
  $query->condition('type', ['simple_collection_listing', 'curated_post_listing', 'collection_listing_publication'], 'IN');
  $relevant_paragraph_ids = $query->execute();
  $paragraphs = \Drupal\paragraphs\Entity\Paragraph::loadMultiple($relevant_paragraph_ids);

  foreach ($paragraphs as $paragraph) {
    $settings = $paragraph->getAllBehaviorSettings();

    if (isset($settings['post_listing']['list_style'])) {
      $settings['list_styles']['list_style'] = $settings['post_listing']['list_style'];
      unset($settings['post_listing']['list_style']);
    }

    if (isset($settings['story_listing']['list_style'])) {
      $settings['list_styles']['list_style'] = $settings['story_listing']['list_style'];
      unset($settings['story_listing']['list_style']);
    }

    $paragraph->setAllBehaviorSettings($settings);
    $paragraph->save();
  }
}

/**
 * Create social footer block.
 */
function ilr_post_update_create_social_footer_block(&$sandbox) {
  $blockEntityManager = \Drupal::service('entity_type.manager')
    ->getStorage('block_content');

  $block = $blockEntityManager->create([
    'type' => 'simple_text',
    'uuid' => '48bd16f4-0fe8-4b1a-800b-089c03c0be23',
    'label_display' => 0,
  ]);

  $block->info = "Social footer";
  $content = '<div class="social-follow">
    <ul class="social-follow__items">
      <li class="social-follow__item"><a class="social-follow__link" href="https://www.linkedin.com/company/cornell-university-ilr-school"><svg class="cu-icon__image" width="1.8em" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><title>LinkedIn</title><use href="/libraries/union/source/images/icons.svg#linkedin"></use></svg></a></li>
      <li class="social-follow__item"><a class="social-follow__link" href="https://facebook.com/ilrschool"><svg class="cu-icon__image" width="1.4em" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><title>Facebook</title><use href="/libraries/union/source/images/icons.svg#facebook"></use></svg></a></li>
      <li class="social-follow__item"><a class="social-follow__link" href="https://twitter.com/CornellILR"><svg class="cu-icon__image" width="2em" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><title>Twitter</title><use href="/libraries/union/source/images/icons.svg#twitter"></use></svg></a></li>
      <li class="social-follow__item"><a class="social-follow__link" href="https://instagram.com/cornellilr"><svg class="cu-icon__image" width="1em" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><title>Instagram</title><use href="/libraries/union/source/images/icons.svg#instagram"></use></svg></a></li>
      <li class="social-follow__item"><a class="social-follow__link" href="https://www.youtube.com/user/CornellUniversityILR?sub_confirmation=1"><svg class="cu-icon__image" width="1.5em" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><title>Youtube</title><use href="/libraries/union/source/images/icons.svg#youtube"></use></svg></a></li>
    </ul>
  </div>';
  $block->body->value = $content;
  $block->body->format = 'full_html';
  $block->save();
}

/**
 * Create copyright block.
 */
function ilr_post_update_create_copyright_block(&$sandbox) {
  $blockEntityManager = \Drupal::service('entity_type.manager')
    ->getStorage('block_content');

  $block = $blockEntityManager->create([
    'type' => 'simple_text',
    'uuid' => 'e0fd4aad-b220-46f2-912c-18aec105a67d',
    'label_display' => 0,
  ]);

  $block->info = "Copyright";
  $content = '<div class="copyright"><p>&copy; [date:custom:Y] Cornell University | ILR School</p></div>';
  $block->body->value = $content;
  $block->body->format = 'full_html';
  $block->save();
}

/**
 * Fix imported image embeds in rich text paragraphs.
 */
function ilr_post_update_fix_imported_image_embeds(&$sandbox) {
  $media_storage = \Drupal::service('entity_type.manager')->getStorage('media');
  $paragraph_storage = \Drupal::service('entity_type.manager')->getStorage('paragraph');
  $query = $paragraph_storage->getQuery();
  $query->condition('type', 'rich_text',);
  $query->condition('field_body', '%[[{"fid":%', 'LIKE');
  $relevant_paragraph_ids = $query->execute();
  $paragraphs = $paragraph_storage->loadMultiple($relevant_paragraph_ids);

  foreach ($paragraphs as $paragraph) {
    $text_content = $paragraph->field_body->value;
    $text_format = 'basic_formatting';

    // Update any embedded media. See https://regex101.com/r/K5FMNj/4 to test
    // this regex.
    if (preg_match_all('/\[\[{"fid":"(\d+)".*"link_text":"?([^",]+)"?.*\]\]/m', $text_content, $matches, PREG_SET_ORDER)) {
      foreach($matches as $match) {
        if ($media = $media_storage->load($match[1])) {
          $link_text = $match[2] !== 'null' ? $match[2] : '';
          $text_content = str_replace($match[0], sprintf('<drupal-media data-link-text="%s" data-entity-type="media" data-entity-uuid="%s"></drupal-media>', $link_text, $media->uuid()), $text_content);
          $paragraph->field_body->value = $text_content;
          $paragraph->field_body->format = 'basic_formatting_with_media';
          $paragraph->save();
        }
      }
    }
  }
}
