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

/**
 * Create 75th branding block.
 */
function ilr_post_update_create_75_branding_block(&$sandbox) {
  $blockEntityManager = \Drupal::service('entity_type.manager')
    ->getStorage('block_content');

  $block = $blockEntityManager->create([
    'type' => 'simple_text',
    'uuid' => '7e1192cc-d8f8-4ef7-8924-874e1e23878a',
    'label_display' => 0,
  ]);

  $block->info = "75th lockup";
  $content = '<a href="/"><svg viewBox="0 0 223.96 48.01" role="img" aria-labelledby="75thlockup" xmlns="http://www.w3.org/2000/svg"><title id="75thlockup">Logo of the ILR School at Cornell University, celebrating its 75th Anniversary</title><defs><style>.a,.b{fill:none;}.b{stroke:#231f20;stroke-width:0.5px;}.c{fill:#231f20;}.d{clip-path:url(#a);}.e{fill:#242021;}.f{fill:#c41230;}</style><clipPath id="a" transform="translate(-.01 .02)"><rect class="a" x=".01" y="-.02" width="39.85" height="39.85"/></clipPath></defs><line class="b" x1="49.82" x2="49.82" y1=".04" y2="39.82"/><path class="c" transform="translate(-.01 .02)" d="M174.09,27.66V10.51l-.25-.25h-3l-.25.25V27.66l.25.25h3Zm-8.2-6.05c0,2.27-1,3.53-2.77,3.53s-2.78-1.26-2.78-3.53,1-3.53,2.78-3.53,2.77,1.26,2.77,3.53m3.53,0c0-4-2.52-6.56-6.3-6.56s-6.31,2.52-6.31,6.56,2.53,6.55,6.31,6.55,6.3-2.52,6.3-6.55m-16.7,0c0,2.27-1,3.53-2.78,3.53s-2.77-1.26-2.77-3.53,1-3.53,2.77-3.53,2.78,1.26,2.78,3.53m3.53,0c0-4-2.52-6.56-6.31-6.56s-6.3,2.52-6.3,6.56,2.52,6.55,6.3,6.55,6.31-2.52,6.31-6.55m-13.66,6.05V20.1c0-3-1.77-5.05-4.79-5.05a4.45,4.45,0,0,0-3.28,1.26h-.25v-5.8l-.25-.25h-3l-.25.25V27.66l.25.25h3l.25-.25v-6.3c0-2.27,1-3.28,2.77-3.28,1.51,0,2,1,2,2.27v7.31l.25.25h3Zm-21.48-6.05c0-2.27,1-3.53,2.78-3.53,1.26,0,2,.5,2.27,1.26l.25.25h3l.26-.25c-.51-3-3-4.29-5.8-4.29-3.79,0-6.31,2.52-6.31,6.56s2.52,6.55,6.31,6.55c2.77,0,5.29-1.26,5.8-4.28l-.26-.25h-3l-.25.25a2.15,2.15,0,0,1-2.27,1.26c-1.77,0-2.78-1.26-2.78-3.53m-4.24,1.26c0-3.53-2.77-5-6.31-5.55s-4.53-1-4.53-2.52c0-1,.75-1.77,3-1.77,2.78,0,3.53.76,3.53,2.27l.25.26h3l.25-.26c0-3-2-5.29-6.81-5.29s-6.8,2.27-6.8,4.79c0,3.53,2.77,4.79,6.3,5.3s4.54,1,4.54,2.77c0,1.51-1.26,2.27-3.53,2.27-3,0-4-1-4-2.78l-.25-.25h-3l-.26.25c0,3.28,2.27,5.8,7.32,5.8,5.29,0,7.31-2.52,7.31-5.29M91.4,15.56c0,1.76-1,2.27-2.52,2.27H84.09l-.25-.26v-4l.25-.25h4.79c1.51,0,2.52.5,2.52,2.27m3.53,0c0-3.53-2.52-5.3-6.05-5.3H80.56l-.25.25V27.66l.25.25h3l.26-.25V21.1l.25-.25h2.52l.5.25,5,6.56.5.25h1.51l.26-.25V25.14l-3.28-4.29V20.6a5,5,0,0,0,3.78-5M78.77,27.66V25.14l-.25-.25H70.7l-.25-.26V10.51l-.25-.25h-3l-.25.25V27.66l.25.25H78.52Zm-15.42,0V10.51l-.25-.25h-3l-.26.25V27.66l.26.25h3Z"/><g class="d"><path class="e" transform="translate(-.01 .02)" d="M19.93.91a19,19,0,1,0,19,19,19,19,0,0,0-19-19Zm0,38.92A19.93,19.93,0,1,1,39.86,19.9,19.95,19.95,0,0,1,19.93,39.83Z"/></g><path class="e" transform="translate(-.01 .02)" d="M19.93,5.86a14,14,0,1,0,14,14,14,14,0,0,0-14-14Zm0,29a15,15,0,1,1,15-15,15,15,0,0,1-15,15Z"/><path class="e" transform="translate(-.01 .02)" d="M11.35,10.75V22.6c0,6,7.32,9.66,8.59,10.26,1.26-.61,8.57-4.34,8.57-10V10.75Zm8.59,23.13-.19-.08c-.38-.17-9.33-4.09-9.33-11.2V9.82h19v13c0,6.82-8.93,10.81-9.32,11l-.18.08Z"/><path class="e" transform="translate(-.01 .02)" d="M13.91,12.14v2.74c0,.09.13.27.45.48s1.07.67,1.4.87c.34-.19,1-.57,1.44-.87.13-.08.41-.28.41-.45V12.14Zm1.83,5.16-.23-.13s-1-.6-1.66-1a1.62,1.62,0,0,1-.87-1.2V11.21h5.56v.46c0,2.1,0,3.2,0,3.27a1.53,1.53,0,0,1-.82,1.2c-.65.44-1.71,1-1.75,1l-.23.12Z"/><path class="e" transform="translate(-.01 .02)" d="M22.51,12.12v2.74c0,.09.13.26.46.48s1.06.67,1.39.87c.34-.19,1-.57,1.45-.87.12-.08.4-.29.41-.45V12.12Zm1.83,5.16-.23-.13s-1-.6-1.66-1a1.64,1.64,0,0,1-.87-1.19V11.18h5.57v3.74a1.52,1.52,0,0,1-.82,1.19c-.66.45-1.71,1-1.76,1l-.23.13Z"/><polygon class="e" points="29.09 18.57 10.48 18.57 10.48 17.65 29.09 17.65 29.09 18.57"/><path class="e" transform="translate(-.01 .02)" d="M19,27.52h0a1.2,1.2,0,0,1,.9.37,1.24,1.24,0,0,1,.92-.37c.57,0,1,0,1.42.08s.86.07,1.51.09h.7V20.78h-1c-.35,0-.73-.05-1.18-.1l-.8-.08a2.78,2.78,0,0,0-.89,0,.52.52,0,0,0-.33.16L20,20.63l-.3.18h0a.77.77,0,0,0-.37-.19h0a3.3,3.3,0,0,0-.87,0l-.81.08c-.45,0-.83.09-1.19.1h-1v6.91h.71c.64,0,1.08-.06,1.51-.09A13.75,13.75,0,0,1,19,27.52ZM19.91,29a.77.77,0,0,1-.59-.34.34.34,0,0,0-.33-.17,12.75,12.75,0,0,0-1.3.07c-.43,0-.9.08-1.58.1s-1.19,0-1.22,0l-.44,0V19.86h1.95c.31,0,.68-.05,1.11-.09l.81-.08a4.41,4.41,0,0,1,1.17,0,1.94,1.94,0,0,1,.43.16,1.69,1.69,0,0,1,.42-.15,3.88,3.88,0,0,1,1.18,0l.81.08c.43,0,.79.08,1.11.09H25.4v8.72l-.44,0s-.54,0-1.21,0-1.16-.06-1.58-.1-.81-.07-1.33-.07c-.18,0-.23.06-.35.19a.75.75,0,0,1-.58.32Z"/><polygon class="e" points="18.03 13.82 13.43 13.82 13.43 13.05 18.03 13.05 18.03 13.82"/><polygon class="e" points="18.03 13.9 13.43 13.9 13.43 12.97 18.03 12.97 18.03 13.9"/><polygon class="e" points="20.27 33.47 19.5 33.47 19.5 17.82 20.27 17.82 20.27 33.47"/><polygon class="e" points="20.23 33.47 19.54 33.47 19.54 17.82 20.23 17.82 20.23 33.47"/><polygon class="e" points="26.31 14.73 24.27 13.36 22.39 14.72 21.94 14.09 24.25 12.42 26.74 14.08 26.31 14.73"/><polygon class="e" points="26.33 14.79 24.33 13.46 22.5 14.78 21.96 14.03 24.31 12.33 26.85 14.02 26.33 14.79"/><path class="e" transform="translate(-.01 .02)" d="M23.6,22.18l-.85,0h-.32l-.65-.06-.4,0a1.33,1.33,0,0,0-.2,0l0-.49a1.24,1.24,0,0,1,.27,0l.4,0,.61.05h.32l.85,0v.49Z"/><path class="e" transform="translate(-.01 .02)" d="M23.6,23.67l-1.17,0a5.27,5.27,0,0,1-.64-.05l-.41,0h-.2l0-.49h.27l.41,0a4.83,4.83,0,0,0,.6,0l1.17,0v.49Z"/><path class="e" transform="translate(-.01 .02)" d="M23.6,25.16H23l-.61,0a5.27,5.27,0,0,1-.63-.05l-.42,0h-.2l0-.48a1.22,1.22,0,0,1,.27,0l.42.05.59,0h.61l.56,0v.48Z"/><path class="e" transform="translate(-.01 .02)" d="M23.6,26.66l-1.12,0h-.05l-.63-.05-.42,0a.69.69,0,0,0-.2,0l0-.49a1.24,1.24,0,0,1,.27,0l.42,0c.22,0,.42.05.59.05h0l1.12,0v.49Z"/><path class="e" transform="translate(-.01 .02)" d="M16.24,22.17v-.49l1.13,0,.66,0,.39,0a1,1,0,0,1,.25,0V22h-.2l-.38,0-.66.06-1.17,0Z"/><path class="e" transform="translate(-.01 .02)" d="M16.24,23.66v-.49l1.17,0c.17,0,.37,0,.61-.05l.4,0h.25l0,.49h-.19l-.39,0c-.25,0-.46.05-.65.05l-1.17,0Z"/><path class="e" transform="translate(-.01 .02)" d="M16.23,25.15v-.49l1.17,0a4.83,4.83,0,0,0,.6-.05l.41,0h.25V25h-.19l-.41,0a5.27,5.27,0,0,1-.64.05l-1.18,0Z"/><path class="e" transform="translate(-.01 .02)" d="M16.23,26.65v-.49l1.1,0h.07l.61-.05.4,0a1.92,1.92,0,0,1,.24,0v.48h-.19l-.39,0-.66.06h-.07l-1.11,0Z"/><polygon class="e" points="13.53 21.12 14.54 21.12 14.54 22.24 13.53 22.24 13.53 21.12"/><polygon class="e" points="13.53 23.7 14.6 23.7 14.6 24.81 13.53 24.81 13.53 23.7"/><polygon class="e" points="13.53 26 14.54 26 14.54 27.11 13.53 27.11 13.53 26"/><polygon class="e" points="25.19 21.34 26.21 21.34 26.21 22.45 25.19 22.45 25.19 21.34"/><polygon class="e" points="25.19 23.92 26.26 23.92 26.26 25.03 25.19 25.03 25.19 23.92"/><polygon class="e" points="25.19 26.22 26.21 26.22 26.21 27.33 25.19 27.33 25.19 26.22"/><path class="e" transform="translate(-.01 .02)" d="M4.3,18a2.39,2.39,0,0,1,0,.6A1.27,1.27,0,0,1,2.91,20a1.32,1.32,0,0,1-1.34-1.54,1.71,1.71,0,0,1,.15-.59l.45.07a1,1,0,0,0-.18.56.91.91,0,0,0,.92.95.89.89,0,0,0,1-.84A1.48,1.48,0,0,0,3.84,18Z"/><path class="e" transform="translate(-.01 .02)" d="M4.46,15.71c.15-.52-.26-.85-.73-1s-1-.06-1.13.45.27.85.72,1,1,.07,1.14-.45ZM2.2,15.06a1.25,1.25,0,0,1,1.68-.87,1.3,1.3,0,1,1-.71,2.5,1.24,1.24,0,0,1-1-1.63Z"/><path class="e" transform="translate(-.01 .02)" d="M4.53,12.36l.08-.16c.12-.23.23-.51-.07-.67s-.44.1-.57.33L3.89,12l.64.34Zm-1.26-.07.27-.51c.26-.5.56-1.11,1.22-.76a.63.63,0,0,1,.3.78h0c.08-.12.25-.12.38-.11l1.12.11-.27.53-.89-.12c-.22,0-.32,0-.42.2l-.08.15,1,.52-.25.47L3.27,12.29Z"/><polygon class="e" points="5.07 9.24 5.48 8.74 7.67 9.25 7.68 9.24 6.13 7.96 6.46 7.57 8.52 9.28 8.11 9.78 5.92 9.28 5.91 9.28 7.46 10.57 7.13 10.96 5.07 9.24"/><polygon class="e" points="7.76 6.33 8.97 5.34 9.23 5.67 8.43 6.32 8.85 6.83 9.58 6.24 9.85 6.57 9.12 7.16 9.59 7.75 10.4 7.09 10.67 7.42 9.45 8.41 7.76 6.33"/><polygon class="e" points="10.39 4.39 10.85 4.13 11.96 6.1 12.86 5.6 13.06 5.97 11.7 6.73 10.39 4.39"/><polygon class="e" points="13.11 3.03 13.61 2.85 14.38 4.97 15.35 4.62 15.49 5.02 14.03 5.55 13.11 3.03"/><path class="e" transform="translate(-.01 .02)" d="M17.4,1.84l.53,0,.13,1.54c0,.46.21.74.61.71s.52-.34.49-.8L19,1.7l.53,0,.14,1.71c.06.72-.32,1-1,1.1s-1.11-.2-1.17-.92L17.4,1.84Z"/><polygon class="e" points="21.37 1.73 22.01 1.81 22.75 3.94 22.75 3.94 23.02 1.95 23.52 2.02 23.17 4.68 22.52 4.59 21.79 2.46 21.78 2.46 21.51 4.45 21.01 4.38 21.37 1.73"/><polygon class="e" points="25.17 2.45 25.68 2.61 24.87 5.17 24.37 5.01 25.17 2.45"/><polygon class="e" points="27.06 3.09 27.57 3.35 27.22 5.53 27.23 5.54 28.78 3.96 29.25 4.2 27.24 6.19 26.66 5.89 27.06 3.09"/><polygon class="e" points="30.54 5.07 31.77 6.03 31.51 6.36 30.7 5.73 30.29 6.25 31.03 6.83 30.77 7.17 30.03 6.58 29.56 7.18 30.38 7.82 30.12 8.15 28.89 7.18 30.54 5.07"/><path class="e" transform="translate(-.01 .02)" d="M32.51,8.33l.11.13c.17.19.4.39.65.17s.07-.45-.1-.65l-.12-.13-.54.48ZM33,7.17l.38.43c.37.43.83.93.27,1.42a.6.6,0,0,1-.82,0h0c.09.12,0,.28,0,.4l-.51,1L31.92,10l.42-.79a.33.33,0,0,0,0-.46l-.11-.12-.84.73L31,8.94l2-1.77Z"/><path class="e" transform="translate(-.01 .02)" d="M35.52,11.43a1.43,1.43,0,0,0-.17-.53.37.37,0,0,0-.53-.16c-.4.23.36,1.13-.42,1.59-.5.3-.93,0-1.2-.44a2.33,2.33,0,0,1-.26-.64l.44-.2a1.41,1.41,0,0,0,.16.6c.11.17.33.35.54.22.44-.26-.33-1.15.43-1.6a.82.82,0,0,1,1.18.38,1.84,1.84,0,0,1,.24.59l-.41.19Z"/><polygon class="e" points="36.69 12.78 36.89 13.28 34.4 14.29 34.2 13.8 36.69 12.78"/><polygon class="e" points="37.15 15.44 36.97 14.7 37.38 14.6 37.87 16.59 37.46 16.69 37.28 15.96 35.08 16.5 34.96 15.98 37.15 15.44"/><polygon class="e" points="36.51 18.9 38.07 17.84 38.11 18.46 37.02 19.15 38.18 19.72 38.21 20.28 36.54 19.43 35.48 19.49 35.45 18.96 36.51 18.9"/><polygon class="e" points="4.7 23.54 5.11 24.96 4.71 25.08 4.44 24.17 3.81 24.35 4.06 25.22 3.65 25.34 3.4 24.47 2.27 24.8 2.12 24.28 4.7 23.54"/><path class="e" transform="translate(-.01 .02)" d="M3.86,27.84c.24.5.77.46,1.2.25s.76-.62.53-1.1-.76-.46-1.19-.25-.78.61-.54,1.1Zm2.11-1a1.25,1.25,0,0,1-.66,1.78A1.26,1.26,0,0,1,3.48,28a1.25,1.25,0,0,1,.67-1.79A1.24,1.24,0,0,1,6,26.81Z"/><path class="e" transform="translate(-.01 .02)" d="M7.11,28.92l.34.42-1.22,1c-.36.28-.5.59-.24.91s.58.25.93,0l1.22-1,.33.42L7.13,31.69a.93.93,0,0,1-1.48-.22A.94.94,0,0,1,5.77,30l1.34-1.06Z"/><polygon class="e" points="9.3 31.51 9.81 31.93 9.33 34.12 9.34 34.13 10.61 32.58 11.01 32.9 9.3 34.98 8.8 34.56 9.28 32.36 9.27 32.35 8 33.91 7.6 33.58 9.3 31.51"/><path class="e" transform="translate(-.01 .02)" d="M11.5,35.8l.27.13a.92.92,0,0,0,.82-1.64l-.27-.13L11.5,35.8ZM12,33.54l.66.33c.73.36,1.26.94.78,1.89s-1.25.87-2,.5l-.65-.32,1.2-2.4Z"/><polygon class="e" points="15.13 34.94 16.65 35.35 16.54 35.76 15.54 35.49 15.37 36.13 16.29 36.37 16.18 36.78 15.26 36.53 15.06 37.26 16.08 37.53 15.97 37.94 14.44 37.53 15.13 34.94"/><path class="e" transform="translate(-.01 .02)" d="M18.25,37.83h.3a.92.92,0,0,0,.1-1.83l-.29,0-.11,1.84Zm-.41-2.29.73,0c.82,0,1.53.36,1.47,1.42s-.8,1.3-1.62,1.26l-.73,0,.15-2.68Z"/><path class="e" transform="translate(-.01 .02)" d="M23.17,35.81h0L23,37l.77-.16-.61-1Zm-.4-.47.59-.12L25,37.62l-.58.12L24,37.19,23,37.42l-.1.65-.54.11.46-2.84Z"/><polygon class="e" points="25.53 36.83 26.04 36.65 26.22 37.16 25.72 37.34 25.53 36.83"/><path class="e" transform="translate(-.01 .02)" d="M27.78,36l.26-.14a.92.92,0,0,0-.88-1.62l-.26.15L27.78,36Zm-1.55-1.73.64-.35c.72-.39,1.49-.49,2,.44s0,1.53-.72,1.92l-.64.35-1.28-2.36Z"/><polygon class="e" points="29.63 34.79 30.08 34.48 30.38 34.92 29.94 35.23 29.63 34.79"/><polygon class="e" points="30.74 31.12 31.08 30.78 32.96 32.69 32.58 33.07 31.13 31.6 31.06 32.17 30.63 32.12 30.74 31.12"/><path class="e" transform="translate(-.01 .02)" d="M34.26,30.59a.36.36,0,0,0,0-.52.53.53,0,0,0-.65.08.5.5,0,0,0,.16.54.39.39,0,0,0,.54-.1Zm-1-.78a.46.46,0,0,0-.13-.47.31.31,0,0,0-.47.07.33.33,0,0,0,0,.5c.18.14.41,0,.57-.1Zm-.12.52a.71.71,0,0,1-.77,0,.73.73,0,0,1-.06-1.11c.28-.38.69-.6,1.05-.34a.72.72,0,0,1,.25.74h0a.76.76,0,0,1,.88,0,.79.79,0,0,1,.08,1.2c-.31.42-.75.63-1.18.3a.72.72,0,0,1-.25-.79Z"/><path class="e" transform="translate(-.01 .02)" d="M35.34,28c.23.11.48.11.6-.12s-.06-.43-.28-.53-.49-.13-.61.1.05.43.29.55Zm-.75-1.56a1.11,1.11,0,0,0-.3.37c-.21.41.1.75.46.94h0a.59.59,0,0,1,0-.59.73.73,0,0,1,1.08-.33A.84.84,0,0,1,36.29,28c-.33.66-1,.53-1.52.25s-1.26-.89-.87-1.66a1.54,1.54,0,0,1,.33-.47l.36.26Z"/><path class="e" transform="translate(-.01 .02)" d="M35.49,23.56l-.3,1,.55.17a.85.85,0,0,1,0-.24.81.81,0,0,1,1.09-.58.92.92,0,0,1,.57,1.26,3.07,3.07,0,0,1-.25.58l-.46-.15a1.41,1.41,0,0,0,.29-.48.5.5,0,0,0-.29-.69c-.33-.1-.54.16-.62.46a1.55,1.55,0,0,0-.07.48l-1.37-.44.43-1.47.39.11Z"/><path class="f" transform="translate(-.01 .02)" d="M215.91,20.66a13.79,13.79,0,0,0-6-1.41H209l.8-2.14h9.45l.11-.11,3.19-8.28v0l-.14-.14H208.7l-.14.14-.6,1.65.14.14h11.6l-1.78,4.69h-9.45l-.07.07-.07.18-1.44,3.82h0l-.7,1.84.1.09h3.52a12.44,12.44,0,1,1-12.2,13.33h4.56a7.7,7.7,0,1,0,7.63-8.6H199l8.93-24,.69-1.8L208.51,0H185.67l-.14.07V.14h0V8.52l.19.13h10.17l-6.95,18.74-.15.36h0l.07.07v0h4.26l.14-.14.33-.9.13-.31h0l.16-.44-.14-.14h-2.16L198,8.56l.65-1.71-.13-.13h-11V1.93H205.9l-8.94,24-.69,1.81.13.13h13.41a5.8,5.8,0,1,1-5.73,5.76v-.89l-.14-.14H195.8l-.14.14v1.65h0a14.17,14.17,0,1,0,20.24-13.72Z"/></svg></a>';
  $block->body->value = $content;
  $block->body->format = 'inline_svg';
  $block->save();
}
