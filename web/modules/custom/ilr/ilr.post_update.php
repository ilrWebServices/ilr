<?php

/**
 * @file
 * Post update functions for the ILR module.
 */

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\ilr\EventSubscriber\CollectionEventSubscriber;
use Drupal\Core\Url;
use Drupal\ilr\Entity\CertificateNode;
use Drupal\ilr\Entity\EventNodeBase;
use Drupal\salesforce_mapping\Entity\MappedObject;

/**
 * Add alt attributes for instructor photos that don't have one.
 */
function ilr_post_update_instructor_photo_alt_attributes(&$sandbox) {
  $modified_media_count = 0;

  // Get all instructor nodes.
  $instructor_nids = \Drupal::entityQuery('node')->condition('type', 'instructor')->execute();
  $instructor_nodes = Node::loadMultiple($instructor_nids);

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
    '%modified_media_count' => $modified_media_count,
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

  $rich_text_paragraphs = Paragraph::loadMultiple($rich_text_paragraph_ids);

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

  // Load the covid vocabulary.
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
 * Change `blog_collection_id` attribute key to `blog_taxonomy_categories`.
 *
 * We do this for the vocabulary collection items for the covid and ILR in the
 * News blogs.
 */
function ilr_post_update_fix_blog_category_collection_item_attributes(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $collection_item_storage = $entity_type_manager->getStorage('collection_item');

  // 17 and 23 are the collection_item ids for the blog vocabularies.
  $blog_cat_collection_items = $collection_item_storage->loadMultiple([17, 23]);

  foreach ($blog_cat_collection_items as $collection_item) {
    $collection_item->setAttribute('blog_taxonomy_categories', TRUE);
    $collection_item->removeAttribute('blog_collection_id');
    $collection_item->save();
  }
}

/**
 * Add the `blog_2_tags` and `blog_4_tags` vocabularies to their collections.
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
  $simple_post_listings = Paragraph::loadMultiple($post_listing_paragraph_ids);

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
  $query->condition('type', [
    'simple_collection_listing',
    'curated_post_listing',
    'collection_listing_publication',
  ], 'IN');
  $relevant_paragraph_ids = $query->execute();
  $paragraphs = Paragraph::loadMultiple($relevant_paragraph_ids);

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
      foreach ($matches as $match) {
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

/**
 * Create 75th logo block.
 */
function ilr_post_update_create_75_logo_block(&$sandbox) {
  $blockEntityManager = \Drupal::service('entity_type.manager')
    ->getStorage('block_content');

  $block = $blockEntityManager->create([
    'type' => 'simple_text',
    'uuid' => 'ae107194-9e53-436b-8821-5c0caf031770',
    'label_display' => 0,
  ]);

  $block->info = "75th logo";
  $content = '<a href="/75"><svg xmlns="http://www.w3.org/2000/svg" role="img" aria-labelledby="75thlogo" viewBox="0 0 372.27 144.71"><title id="75thlogo">Logo for the ILR School\'s 75th Anniversary</title><defs><style>.a{fill:#c41230;}.b{fill:#231f20;}</style></defs><path class="a" d="M178.1,62.29a41.85,41.85,0,0,0-18.23-4.23h-2.75l2.41-6.45H188l.35-.35L198,26.31v-.08l-.42-.41H156.33l-.42.41-1.8,5,.43.42h35l-5.38,14.14H155.66l-.23.22-.19.52-4.35,11.53h0l-2.1,5.53.29.3H159.7c20.34,0,36.89,16.82,36.89,37.5S180,138.89,159.7,138.89c-19.45,0-35.44-15.39-36.8-34.82h13.77a23.19,23.19,0,1,0,23-25.93h-32.5L154.13,5.82,156.19.4l-.4-.4H86.9l-.21.21-.21.21h0V25.68l.57.41h30.66l-21,56.52-.43,1.07v0l.22.21,0,.06H113l.42-.42,1-2.72.37-.91,0,0,.5-1.33-.42-.42h-10.2l19.37-52.32,2-5.16-.4-.39H92.45V5.82H147.9L121,78.14l-2.08,5.46.37.37h40.44a17.5,17.5,0,1,1-17.27,17.38V98.66l-.42-.41H117.46l-.42.41v5h0c1.18,22.84,19.86,41.05,42.66,41.05,23.55,0,42.71-19.44,42.71-43.33A43.41,43.41,0,0,0,178.1,62.29Z"/><polygon class="b" points="79.46 78.23 6.47 78.23 6.47 78.23 5.98 77.74 5.98 77.75 5.98 26.72 6.45 26.25 44.37 26.25 44.84 25.78 44.84 20.73 44.38 20.27 0.46 20.27 0 20.73 0 83.76 0.44 84.2 79.46 84.2 79.93 83.73 79.93 78.7 79.46 78.23"/><polygon class="b" points="35.87 62.34 35.87 36.39 35.4 35.92 29.84 35.92 29.38 36.39 29.38 67.9 29.84 68.37 50.7 68.37 51.16 67.9 51.16 63.27 50.7 62.8 36.33 62.8 35.87 62.34"/><path class="b" d="M73.91,54.92a9.17,9.17,0,0,0,7-9.26c0-6.49-4.64-9.74-11.13-9.74H54.45l-.47.47V67.9l.47.47H60l.46-.47V55.85l.47-.46h4.63l.93.46L75.77,67.9l.92.47h2.79l.46-.47V63.27l-6-7.88Zm-4.17-5.09h-8.8l-.47-.47V42l.47-.47h8.8c2.78,0,4.64.93,4.64,4.18S72.52,49.83,69.74,49.83Z"/><polygon class="b" points="16.34 36.39 16.34 67.9 16.8 68.37 22.36 68.37 22.82 67.9 22.82 36.39 22.36 35.92 16.8 35.92 16.34 36.39"/><path class="b" d="M209.2,60.08l-.48-.24h-5.55l-.48.24-1,2.66-.25.24h-2.89l-.24-.24V59.6l5.31-13.27.24-.24H208l.24.24,5.31,13.27v3.14l-.24.24h-2.9l-.24-.24ZM204.14,56l.24.24h3.14l.24-.24-1.69-4.58h-.24Z"/><path class="b" d="M216,46.09h4.1l.48.24,5.79,9.17h.24V46.33l.24-.24h3.86l.24.24V62.74l-.24.24h-4.1l-.48-.24-5.79-9.17h-.24v9.17l-.24.24H216l-.24-.24V46.33Z"/><path class="b" d="M234.53,46.09h4.1l.49.24,5.79,9.17h.24V46.33l.24-.24h3.86l.24.24V62.74l-.24.24h-4.1l-.49-.24-5.79-9.17h-.24v9.17l-.24.24h-3.86l-.24-.24V46.33Z"/><path class="b" d="M257,63h-3.86l-.25-.24V46.33l.25-.24H257l.24.24V62.74Z"/><path class="b" d="M269.51,62.74l-.24.24h-4.1l-.24-.24-5.31-13.27V46.33l.24-.24H263l.24.24,3.86,11.1h.24l3.86-11.1.24-.24h3.13l.25.24v3.14Z"/><path class="b" d="M289.77,63h-12.3l-.24-.24V46.33l.24-.24h12.3l.24.24v3.14l-.24.24h-8l-.24.24v2.41l.24.25h7.48l.24.24V56l-.24.24h-7.48l-.24.25v2.65l.24.24h8l.24.24v3.14Z"/><path class="b" d="M304.49,56.22v.25l2.65,3.13v3.14l-.24.24H305l-.48-.24L299.66,57l-.48-.24h-1.69l-.24.24v5.79L297,63h-3.86l-.24-.24V46.33l.24-.24h8.44c3.62,0,6,1.93,6,5.31A4.91,4.91,0,0,1,304.49,56.22Zm-2.9-6.51h-4.1l-.24.24v2.9l.24.24h4.1c1,0,1.69-.24,1.69-1.69S302.56,49.71,301.59,49.71Z"/><path class="b" d="M317,56.47l-1.69-.25c-5.07-.72-6-3.13-6-5.54,0-2.66,2.17-4.83,7-4.83,4.35,0,7,1.69,7,5.07l-.24.24H319.2l-.24-.24c0-1-.73-1.69-2.66-1.69s-2.89.48-2.89,1.45,1,1.2,2.65,1.44l1.69.24c5.07.73,6,2.9,6,5.55s-1.93,5.31-7.23,5.31c-4.83,0-7.48-1.93-7.48-5.55l.24-.24h3.86l.24.24c0,1.45,1,2.17,3.14,2.17,1.93,0,3.13-.48,3.13-1.69S318.72,56.71,317,56.47Z"/><path class="b" d="M335.6,60.08l-.48-.24h-5.55l-.48.24-1,2.66-.24.24H325l-.24-.24V59.6l5.3-13.27.24-.24h4.11l.24.24,5.3,13.27v3.14l-.24.24h-2.89l-.24-.24ZM330.54,56l.24.24h3.13l.24-.24-1.68-4.58h-.25Z"/><path class="b" d="M353.69,56.22v.25l2.66,3.13v3.14l-.24.24h-1.93l-.49-.24L348.87,57l-.48-.24H346.7l-.24.24v5.79l-.24.24h-3.86l-.25-.24V46.33l.25-.24h8.44c3.62,0,6,1.93,6,5.31A4.9,4.9,0,0,1,353.69,56.22Zm-2.89-6.51h-4.1l-.24.24v2.9l.24.24h4.1c1,0,1.69-.24,1.69-1.69S351.76,49.71,350.8,49.71Z"/><path class="b" d="M367.44,57v5.79l-.24.24h-3.86l-.24-.24V57l-4.82-7.48V46.33l.24-.24h2.17l.48.24L365,52.85h.48l3.86-6.52.49-.24H372l.24.24v3.14Z"/><path class="b" d="M244.1,76.21,244,76.1V70h-.11l-1.32,1.32h-.21l-.11-.11v-.88l1.64-1.64.22-.11H245l.11.11V76.1l-.11.11Z"/><path class="b" d="M252.76,76.31a2,2,0,0,1-2.19-1.86l.1-.11h.88l.11.11c.11.66.55.88,1.21.88,1.53,0,1.53-1.75,1.53-2.41h-.11a2,2,0,0,1-1.53.55,2.52,2.52,0,0,1,.11-5c1.75,0,2.74.87,2.74,3.72C255.61,75.22,254.62,76.31,252.76,76.31Zm.11-3.83a1.54,1.54,0,0,0,0-3.07,1.54,1.54,0,0,0,0,3.07Z"/><path class="b" d="M265.14,76.21h-.88l-.11-.11v-2L264,74h-3.61l-.11-.11V73l.11-.22,3.51-4.16.21-.11h1l.11.11V76.1Zm-1-6.25H264l-2.52,3V73H264l.11-.11Z"/><path class="b" d="M273.25,76.31a2.23,2.23,0,0,1-2.52-2.19l.11-.11h.88l.11.11c.11.66.32,1.21,1.42,1.21s1.53-.66,1.53-1.75-.32-1.65-1.42-1.65a1.47,1.47,0,0,0-1.42.88l-.22.11-.77-.11-.11-.11.22-4,.11-.11h4.27l.11.11v.76l-.11.11h-3.29l-.11.11-.1,2h.1a1.85,1.85,0,0,1,1.54-.65,2.24,2.24,0,0,1,2.3,2.52C275.88,75.44,274.89,76.31,273.25,76.31Z"/><path class="b" d="M284.1,73.9h-3l-.11-.1V73l.11-.11h3l.11.11v.77Z"/><path class="b" d="M293.74,76.21h-4.93l-.11-.11v-.88l.11-.22,2.2-1.75c1.09-.88,1.53-1.43,1.53-2.41,0-.77-.33-1.43-1.32-1.43a1.28,1.28,0,0,0-1.42,1.43l-.11.11h-.88l-.11-.11a2.27,2.27,0,0,1,2.52-2.41,2.2,2.2,0,0,1,2.41,2.3,3.63,3.63,0,0,1-1.75,3.07l-1.64,1.31v.11h3.5l.11.11v.77Z"/><path class="b" d="M301.31,68.43c1.53,0,2.74,1,2.74,3.94s-1.21,3.94-2.74,3.94-2.74-1-2.74-3.94S299.77,68.43,301.31,68.43Zm0,6.9c1,0,1.64-.77,1.64-3s-.66-3-1.64-3-1.65.77-1.65,3S300.32,75.33,301.31,75.33Z"/><path class="b" d="M313.8,76.21h-4.93l-.11-.11v-.88l.11-.22,2.19-1.75c1.1-.88,1.53-1.43,1.53-2.41,0-.77-.32-1.43-1.31-1.43a1.27,1.27,0,0,0-1.42,1.43l-.11.11h-.88l-.11-.11a2.27,2.27,0,0,1,2.52-2.41,2.21,2.21,0,0,1,2.41,2.3,3.65,3.65,0,0,1-1.75,3.07l-1.65,1.31v.11h3.51l.11.11v.77Z"/><path class="b" d="M321.36,68.43c1.54,0,2.74,1,2.74,3.94s-1.2,3.94-2.74,3.94-2.74-1-2.74-3.94S319.83,68.43,321.36,68.43Zm0,6.9c1,0,1.65-.77,1.65-3s-.66-3-1.65-3-1.64.77-1.64,3S320.38,75.33,321.36,75.33Z"/><path class="a" d="M209,33.73h-2.82l-.15.14v7.47l-.14.14h-2.25l-.14-.14V33.87l-.14-.14h-2.82l-.14-.14V31.76l.14-.15H209l.14.15v1.83Z"/><path class="a" d="M218.86,41.48h-2.25l-.14-.14V37.68l-.15-.14h-3.24l-.14.14v3.66l-.14.14h-2.26l-.14-.14V31.76l.14-.15h2.26l.14.15v3.52l.14.14h3.24l.15-.14V31.76l.14-.15h2.25l.14.15v9.58Z"/></svg></a>';
  $block->body->value = $content;
  $block->body->format = 'inline_svg';
  $block->save();
}

/**
 * Create 75th video block.
 */
function ilr_post_update_create_75_video_block(&$sandbox) {
  $blockEntityManager = \Drupal::service('entity_type.manager')
    ->getStorage('block_content');

  $block = $blockEntityManager->create([
    'type' => 'simple_text',
    'uuid' => 'e2a92dd5-f370-492f-a1cc-669014e5e9cf',
    'label_display' => 0,
  ]);

  $block->info = "75th video banner";
  $content = '<div class="cu-banner--video cu-banner">
  <div class="cu-banner__media">
    <div class="video-pause">
      <div aria-pressed="false"
        class="cu-icon--inline cu-icon cu-icon--play pause" role="button"><svg
          class="cu-icon__image" viewBox="0 0 100 100" width="3em"
          xmlns="http://www.w3.org/2000/svg">
          <use href="/libraries/union/source/images/icons.svg#pause"></use>
        </svg>
        <div class="cu-icon__label sr-only">Pause Video</div>
      </div>
      <div aria-pressed="false"
        class="cu-icon--inline cu-icon cu-icon--play play visually-hidden" role="button">
        <svg class="cu-icon__image" viewBox="0 0 100 100" width="3em"
          xmlns="http://www.w3.org/2000/svg">
          <use href="/libraries/union/source/images/icons.svg#play"></use>
        </svg>
        <div class="cu-icon__label sr-only">Play Video</div>
      </div>
    </div>
    <video autoplay="" class="cu-video" loop="" muted=""
      src="https://ilr-images.s3.amazonaws.com/video/ilr_hero_clip_75_202008_500.mp4">&nbsp;</video>
  </div>
</div>';
  $block->body->value = $content;
  $block->body->format = 'full_html';
  $block->save();
}

/**
 * Create Scheinman Institute logo block.
 */
function ilr_post_update_create_scheinman_logo_block(&$sandbox) {
  $blockEntityManager = \Drupal::service('entity_type.manager')
    ->getStorage('block_content');

  $block = $blockEntityManager->create([
    'type' => 'simple_text',
    'uuid' => '1544c7cf-5279-483b-bd8e-37aa8b260d77',
    'label_display' => 0,
  ]);

  $block->info = "Scheinman Institute logo";
  $content = '<a href="/scheinman-institute"><svg version="1.1" viewBox="0 0 949 123" xmlns="http://www.w3.org/2000/svg"><title>ILR Scheinman Institute Logo</title><defs><style>.black{fill:#222;}.red{fill:#99242F;}</style></defs><path class="black" d="m32.949 92.751h10.615l0.884-0.884v-60.147l-0.884-0.884h-10.615l-0.884 0.884v60.147l0.884 0.884zm357.87 29.323l-1e-3 -9.601-0.899-0.897h-376.67l1e-3 -2e-3 -0.937-0.938-0.016 0.015v-97.379l0.9-0.901h72.366l0.896-0.896v-9.63l-0.877-0.877h-83.814l-0.875 0.874v120.29l0.846 0.846h388.18l0.905-0.905zm-235.61-72.664c0-12.382-8.845-18.574-21.228-18.574h-29.189l-0.884 0.884v60.147l0.884 0.884h10.614l0.885-0.884v-22.997l0.885-0.885h8.845l1.769 0.885 17.689 22.997 1.77 0.884h5.307l0.884-0.884v-8.845l-11.499-15.037v-0.884c7.077-1.769 13.268-7.961 13.268-17.691zm-12.383 0c0 6.192-3.538 7.961-8.845 7.961h-16.805l-0.885-0.884v-14.152l0.885-0.885h16.805c5.307 0 8.845 1.769 8.845 7.96zm-45.188 43.341l0.884-0.884v-8.845l-0.884-0.884h-27.42l-0.884-0.885v-49.533l-0.885-0.884h-10.614l-0.885 0.884v60.147l0.885 0.884h39.803z"/><path class="red" d="m205.92 93.593c-17.69 0-25.651-8.845-25.651-20.344l0.885-0.884h10.614l0.884 0.884c0 6.192 3.539 9.731 14.152 9.731 7.961 0 12.384-2.654 12.384-7.961 0-6.192-3.538-7.961-15.921-9.731-12.384-1.768-22.113-6.191-22.113-18.574 0-8.845 7.076-16.806 23.881-16.806 16.806 0 23.882 7.961 23.882 18.574l-0.885 0.886h-10.613l-0.885-0.886c0-5.307-2.654-7.959-12.383-7.959-7.96 0-10.615 2.652-10.615 6.191 0 5.308 3.538 7.076 15.922 8.845 12.383 1.77 22.113 7.076 22.113 19.46 0 9.729-7.076 18.574-25.651 18.574"/><path class="red" d="m257.07 82.98c4.422 0 7.076-1.77 7.96-4.424l0.885-0.883h10.614l0.884 0.883c-1.768 10.615-10.614 15.037-20.343 15.037-13.268 0-22.114-8.845-22.114-22.997 0-14.153 8.846-22.998 22.114-22.998 9.729 0 18.575 4.424 20.343 15.037l-0.884 0.885h-10.614l-0.885-0.885c-0.884-2.654-3.538-4.422-7.96-4.422-6.192 0-9.73 4.422-9.73 12.383 0 7.96 3.538 12.384 9.73 12.384"/><path class="red" d="m322.67 92.709h-10.613l-0.885-0.884v-25.651c0-4.423-1.769-7.961-7.076-7.961-6.192 0-9.73 3.538-9.73 11.499v22.113l-0.884 0.884h-10.615l-0.883-0.884v-60.148l0.883-0.885h10.615l0.884 0.885v20.345h0.885c1.769-1.77 5.307-4.424 11.498-4.424 10.615 0 16.806 7.077 16.806 17.69v26.537l-0.885 0.884"/><path class="red" d="m357.32 65.288c0-0.884-0.885-7.075-7.961-7.075-7.077 0-7.961 6.191-7.961 7.075l0.884 0.886h14.153l0.885-0.886zm13.266 8.845l-0.885 0.886h-27.419l-0.884 0.884c0 1.77 1.769 7.077 8.844 7.077 2.654 0 5.308-0.886 6.193-2.654l0.885-0.885h10.613l0.885 0.885c-0.885 5.308-5.307 13.267-18.576 13.267-15.036 0-22.112-10.613-22.112-22.997 0-12.383 7.076-22.998 21.229-22.998 14.152 0 21.227 10.615 21.227 22.998v3.537z"/><path class="red" d="m386.81 92.709h-10.615l-0.884-0.884v-42.457l0.884-0.886h10.615l0.883 0.886v42.457l-0.883 0.884zm0-51.302h-10.615l-0.884-0.884v-8.846l0.884-0.885h10.615l0.883 0.885v8.846l-0.883 0.884z"/><path class="red" d="m435.46 92.709h-10.614l-0.885-0.884v-25.651c0-4.423-1.769-7.961-7.075-7.961-6.192 0-9.731 3.538-9.731 11.499v22.113l-0.883 0.884h-10.615l-0.884-0.884v-42.457l0.884-0.886h9.73l0.885 0.886 0.883 2.654h0.885c1.77-1.77 5.308-4.424 11.499-4.424 10.614 0 16.806 7.077 16.806 17.69v26.537l-0.885 0.884"/><path class="red" d="m509.38 92.709h-10.615l-0.884-0.884v-25.651c0-4.423-1.77-7.961-7.077-7.961s-7.961 3.538-7.961 9.729v23.883l-0.884 0.884h-10.614l-0.884-0.884v-25.651c0-4.423-1.769-7.961-7.077-7.961s-7.96 3.538-7.96 9.729v23.883l-0.885 0.884h-10.615l-0.884-0.884v-42.457l0.884-0.886h9.73l0.885 0.886 0.885 2.654h0.885s2.653-4.424 10.614-4.424c7.075 0 10.613 3.538 12.383 6.192h0.884s3.539-6.192 13.268-6.192c10.614 0 16.806 6.192 16.806 17.69v26.537l-0.884 0.884"/><path class="red" d="m540.18 74.133s-1.77-0.884-5.307-0.884c-5.307 0-7.961 1.77-7.961 5.307 0 2.654 1.77 4.424 6.191 4.424 4.424 0 7.961-2.654 7.961-6.193v-1.768l-0.884-0.886zm12.383 18.576h-9.729l-0.886-0.884-0.884-1.77h-0.884s-3.537 3.538-9.731 3.538c-9.728 0-15.92-5.308-15.92-14.152 0-13.267 12.383-15.037 19.459-15.037 3.539 0 6.192 0.884 6.192 0.884l0.884-0.884v-0.884c0-4.423-0.884-5.307-6.191-5.307-2.654 0-5.307 0-6.191 2.654l-0.886 0.884h-10.613l-0.886-0.884c0-5.308 4.424-13.269 18.576-13.269s18.573 7.077 18.573 20.344v23.883l-0.883 0.884z"/><path class="red" d="m600.78 92.709h-10.614l-0.885-0.884v-25.651c0-4.423-1.769-7.961-7.076-7.961-6.191 0-9.729 3.538-9.729 11.499v22.113l-0.886 0.884h-10.614l-0.884-0.884v-42.457l0.884-0.886h9.73l0.884 0.886 0.886 2.654h0.883c1.769-1.77 5.308-4.424 11.499-4.424 10.615 0 16.806 7.077 16.806 17.69v26.537l-0.884 0.884"/><polyline class="red" points="644.12 92.709 633.51 92.709 632.62 91.825 632.62 31.677 633.51 30.792 644.12 30.792 645 31.677 645 91.825 644.12 92.709"/><path class="red" d="m694.54 92.709h-10.614l-0.884-0.884v-25.651c0-4.423-1.768-7.961-7.075-7.961-6.191 0-9.73 3.538-9.73 11.499v22.113l-0.885 0.884h-10.615l-0.883-0.884v-42.457l0.883-0.886h9.731l0.884 0.886 0.885 2.654h0.884c1.769-1.77 5.306-4.424 11.499-4.424 10.614 0 16.806 7.077 16.806 17.69v26.537l-0.886 0.884"/><path class="red" d="m719.04 93.593c-12.384 0-19.459-6.191-19.459-15.037l0.884-0.883h10.614l0.885 0.883c0 3.538 2.653 4.424 7.076 4.424 4.422 0 7.076-0.886 7.076-3.539 0-3.538-3.538-3.538-8.846-4.422-5.306-0.886-16.805-3.539-16.805-14.152 0-7.077 5.307-13.269 18.575-13.269 10.615 0 18.574 4.424 18.574 13.269l-0.884 0.884h-10.614l-0.885-0.884c0-1.77-1.769-2.654-6.191-2.654-4.423 0-6.19 0.884-6.19 3.538 0 3.537 5.305 3.537 10.612 4.423 5.307 0.884 15.038 2.653 15.038 13.267 0 7.076-5.308 14.152-19.46 14.152"/><path class="red" d="m760.33 79.441c0 1.769 0.884 2.653 2.653 2.653h7.961l0.885 0.886v8.845l-0.885 0.884h-7.961c-7.961 0-15.037-3.538-15.037-13.268v-19.46l-0.884-0.884h-6.192l-0.884-0.884v-8.845l0.884-0.886h6.192l0.884-0.884v-7.961l0.885-0.884h10.615l0.884 0.884v7.961l0.884 0.884h9.73l0.885 0.886v8.845l-0.885 0.884h-9.73l-0.884 0.884v19.46"/><path class="red" d="m788.7 92.709h-10.614l-0.884-0.884v-42.457l0.884-0.886h10.614l0.885 0.886v42.457l-0.885 0.884zm0-51.302h-10.614l-0.884-0.884v-8.846l0.884-0.885h10.614l0.885 0.885v8.846l-0.885 0.884z"/><path class="red" d="m813.06 79.441c0 1.769 0.884 2.653 2.654 2.653h7.961l0.885 0.886v8.845l-0.885 0.884h-7.961c-7.961 0-15.037-3.538-15.037-13.268v-19.46l-0.885-0.884h-6.191l-0.884-0.884v-8.845l0.884-0.886h6.191l0.885-0.884v-7.961l0.884-0.884h10.614l0.885 0.884v7.961l0.884 0.884h9.731l0.885 0.886v8.845l-0.885 0.884h-9.731l-0.884 0.884v19.46"/><path class="red" d="m829.62 48.482h10.615l0.884 0.886v25.651c0 4.422 1.77 7.961 7.075 7.961 6.194 0 8.847-4.424 8.847-10.615v-22.997l0.884-0.886h10.615l0.884 0.886v42.457l-0.884 0.884h-9.73l-0.885-0.884-0.884-2.654h-0.886s-2.653 4.422-10.613 4.422c-10.614 0-16.806-7.076-16.806-17.69v-26.535l0.884-0.886"/><path class="red" d="m893.01 79.441c0 1.769 0.884 2.653 2.653 2.653h7.961l0.884 0.886v8.845l-0.884 0.884h-7.961c-7.961 0-15.038-3.538-15.038-13.268v-19.46l-0.884-0.884h-6.191l-0.884-0.884v-8.845l0.884-0.886h6.191l0.884-0.884v-7.961l0.886-0.884h10.614l0.885 0.884v7.961l0.884 0.884h9.73l0.884 0.886v8.845l-0.884 0.884h-9.73l-0.884 0.884v19.46"/><path class="red" d="m935.37 65.288c0-0.884-0.884-7.075-7.961-7.075-7.075 0-7.959 6.191-7.959 7.075l0.884 0.886h14.152l0.884-0.886zm13.267 8.845l-0.88 0.886h-27.423l-0.884 0.884c0 1.77 1.768 7.077 8.845 7.077 2.654 0 5.308-0.886 6.191-2.654l0.884-0.885h10.617l0.88 0.885c-0.88 5.308-5.31 13.267-18.572 13.267-15.038 0-22.113-10.613-22.113-22.997 0-12.383 7.075-22.998 21.227-22.998 14.148 0 21.228 10.615 21.228 22.998v3.537z"/>
</g></svg></a>';
  $block->body->value = $content;
  $block->body->format = 'inline_svg';
  $block->save();
}

/**
 * Create Worker Institute logo block.
 */
function ilr_post_update_create_worker_logo_block(&$sandbox) {
  $blockEntityManager = \Drupal::service('entity_type.manager')
    ->getStorage('block_content');

  $block = $blockEntityManager->create([
    'type' => 'simple_text',
    'uuid' => 'ea0f8e00-bfa4-442c-ac47-1e51dc517aa7',
    'label_display' => 0,
  ]);

  $block->info = "Worker Institute logo";
  $content = '<a href="/worker-institute"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800.52 122.01"><defs><style>.a{fill:#231f20;}.b{fill:#aa182c;}</style></defs><path class="a" d="M153.93,143.79l.89-.88v-8.85l-.89-.88H126.51l-.88-.89V82.76l-.89-.88H114.13l-.88.88v60.15l.88.88Zm45.19-43.34c0,6.19-3.54,8-8.84,8H173.47l-.88-.88V93.37l.88-.88h16.81c5.3,0,8.84,1.77,8.84,8m12.38,0c0-12.38-8.84-18.57-21.22-18.57H161.09l-.89.88v60.15l.89.88H171.7l.89-.88v-23l.88-.88h8.85l1.76.88,17.69,23,1.77.88h5.31l.89-.88v-8.85l-11.5-15v-.89c7.07-1.77,13.26-8,13.26-17.69m163.19,72.66v-9.6l-.9-.89H69.54l-.94-.93h0V64.31l.89-.9h72.37l.9-.9V52.88l-.88-.87H58.06l-.88.87V173.17L58,174H373.78ZM89.24,143.79H99.85l.89-.88V82.76l-.89-.88H89.24l-.88.88v60.15Z" transform="translate(-57.18 -52.01)"/><path class="b" d="M280.78,81.83l.89.89,11.49,40.69h.89l8.84-40.69.89-.89h8.84l.89.89v8.84l-13.27,51.31-.88.88h-11.5l-.89-.88-11.5-42.46h-.88l-11.5,42.46-.88.88h-11.5l-.89-.88L236.56,91.56V82.72l.88-.89h8.85l.88.89L256,123.41h.88l11.5-40.69.88-.89Z" transform="translate(-57.18 -52.01)"/><path class="b" d="M335.44,144.63c-13.26,0-22.11-8.84-22.11-23s8.85-23,22.11-23,22.12,8.84,22.12,23S348.71,144.63,335.44,144.63Zm0-35.38c-6.19,0-9.73,4.43-9.73,12.39S329.25,134,335.44,134s9.73-4.42,9.73-12.38S341.64,109.25,335.44,109.25Z" transform="translate(-57.18 -52.01)"/><path class="b" d="M390.74,110.14h-6.19c-7.08,0-9.73,3.54-9.73,11.5v21.23l-.88.88H363.32l-.88-.88V100.41l.88-.89h9.73l.89.89.88,2.65h.89s1.76-3.54,9.73-3.54h5.3l.89.89v8.84Z" transform="translate(-57.18 -52.01)"/><path class="b" d="M433.73,143.75h-5.31l-1.77-.88L414.27,129.6h-.88l-5.31,5.3-.89,1.77v6.2l-.88.88H395.69l-.88-.88V82.72l.88-.89h10.62l.88.89V118.1h.89l17.69-17.69,1.77-.89h5.3l.89.89v8.84l-10.62,10.62v.88L434.61,134v8.85Z" transform="translate(-57.18 -52.01)"/><path class="b" d="M479.46,125.17l-.89.89H451.15l-.88.88c0,1.77,1.77,7.08,8.84,7.08,2.66,0,5.31-.88,6.2-2.65l.88-.89H476.8l.89.89c-.89,5.3-5.31,13.26-18.58,13.26-15,0-22.11-10.61-22.11-23s7.08-23,21.23-23,21.23,10.61,21.23,23Zm-13.27-8.84a8,8,0,0,0-15.92,0l.88.88h14.16Z" transform="translate(-57.18 -52.01)"/><path class="b" d="M512.49,110.14H506.3c-7.08,0-9.73,3.54-9.73,11.5v21.23l-.88.88H485.07l-.88-.88V100.41l.88-.89h9.73l.89.89.88,2.65h.89s1.76-3.54,9.73-3.54h5.3l.89.89v8.84Z" transform="translate(-57.18 -52.01)"/><path class="b" d="M553.18,143.75H542.56l-.88-.88V82.72l.88-.89h10.62l.88.89v60.15Z" transform="translate(-57.18 -52.01)"/><path class="b" d="M603.6,143.75H593l-.88-.88V117.21c0-4.42-1.77-8-7.08-8-6.19,0-9.73,3.54-9.73,11.5v22.12l-.88.88H563.79l-.88-.88V100.41l.88-.89h9.73l.89.89.88,2.65h.88a15.58,15.58,0,0,1,11.5-4.42c10.62,0,16.81,7.08,16.81,17.69v26.54Z" transform="translate(-57.18 -52.01)"/><path class="b" d="M628.1,144.63c-12.38,0-19.46-6.19-19.46-15l.89-.89h10.61l.89.89c0,3.54,2.65,4.42,7.07,4.42s7.08-.88,7.08-3.54c0-3.54-3.54-3.54-8.85-4.42s-16.8-3.54-16.8-14.15c0-7.08,5.3-13.27,18.57-13.27,10.62,0,18.58,4.42,18.58,13.27l-.89.88H635.18l-.89-.88c0-1.77-1.76-2.66-6.19-2.66s-6.19.89-6.19,3.54c0,3.54,5.31,3.54,10.62,4.42s15,2.66,15,13.27C647.56,137.56,642.25,144.63,628.1,144.63Z" transform="translate(-57.18 -52.01)"/><path class="b" d="M669.39,130.48a2.35,2.35,0,0,0,2.65,2.66h8l.88.88v8.85l-.88.88h-8c-8,0-15-3.54-15-13.27V111l-.89-.88h-6.19l-.88-.89v-8.84l.88-.89h6.19l.89-.88v-8l.88-.89h10.62l.88.89v8l.89.88H680l.88.89v8.84l-.88.89h-9.73l-.89.88Z" transform="translate(-57.18 -52.01)"/><path class="b" d="M697.76,92.45H687.14l-.88-.89V82.72l.88-.89h10.62l.88.89v8.84Zm0,51.3H687.14l-.88-.88V100.41l.88-.89h10.62l.88.89v42.46Z" transform="translate(-57.18 -52.01)"/><path class="b" d="M722.12,130.48a2.35,2.35,0,0,0,2.66,2.66h8l.88.88v8.85l-.88.88h-8c-8,0-15-3.54-15-13.27V111l-.88-.88h-6.19l-.89-.89v-8.84l.89-.89h6.19l.88-.88v-8l.89-.89h10.61l.88.89v8l.89.88h9.73l.88.89v8.84l-.88.89H723l-.89.88Z" transform="translate(-57.18 -52.01)"/><path class="b" d="M738.68,99.52H749.3l.88.89v25.65c0,4.42,1.77,8,7.08,8,6.19,0,8.84-4.42,8.84-10.61v-23l.89-.89H777.6l.89.89v42.46l-.89.88h-9.73l-.88-.88-.89-2.66h-.88s-2.66,4.42-10.62,4.42c-10.61,0-16.8-7.07-16.8-17.69V100.41Z" transform="translate(-57.18 -52.01)"/><path class="b" d="M802.07,130.48a2.35,2.35,0,0,0,2.65,2.66h8l.88.88v8.85l-.88.88h-8c-8,0-15-3.54-15-13.27V111l-.88-.88h-6.19l-.89-.89v-8.84l.89-.89h6.19l.88-.88v-8l.89-.89h10.61l.89.89v8l.88.88h9.73l.88.89v8.84l-.88.89H803l-.88.88Z" transform="translate(-57.18 -52.01)"/><path class="b" d="M857.7,125.17l-.88.89H829.4l-.89.88c0,1.77,1.77,7.08,8.85,7.08,2.65,0,5.3-.88,6.19-2.65l.88-.89h10.62l.88.89c-.88,5.3-5.31,13.26-18.57,13.26-15,0-22.12-10.61-22.12-23s7.08-23,21.23-23,21.23,10.61,21.23,23Zm-13.27-8.84a8,8,0,0,0-15.92,0l.89.88h14.15Z" transform="translate(-57.18 -52.01)"/></svg></a>';
  $block->body->value = $content;
  $block->body->format = 'inline_svg';
  $block->save();
}

/**
 * Create the aliases for collection item paths.
 */
function ilr_post_update_create_collection_item_aliases(&$sandbox) {
  $collection_items = [];
  $pathauto_generator = \Drupal::service('pathauto.generator');
  $result = \Drupal::entityQuery('collection_item')->execute();
  $collection_item_storage = \Drupal::entityTypeManager()->getStorage('collection_item');
  $collection_items = array_merge($collection_items, $collection_item_storage->loadMultiple($result));

  foreach ($collection_items as $collection_item) {
    if ($collection_item->item->entity instanceof ContentEntityInterface) {
      $pathauto_generator->updateEntityAlias($collection_item, 'update');
    }
  }
}

/**
 * Remove all auto-generated term pathauto patterns.
 */
function ilr_post_update_remove_term_patterns(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $pathauto_pattern_storage = $entity_type_manager->getStorage('pathauto_pattern');

  foreach ($pathauto_pattern_storage->loadMultiple() as $pattern) {
    if (strpos($pattern->id(), 'blog_') !== 0 || $pattern->getType() !== 'canonical_entities:taxonomy_term') {
      continue;
    }

    $pattern->delete();
  }
}

/**
 * Move ILR in the News blog items to the News blog.
 */
function ilr_post_update_move_ilr_in_the_news(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $collection_storage = $entity_type_manager->getStorage('collection');
  $term_storage = $entity_type_manager->getStorage('taxonomy_term');
  $vocab_storage = $entity_type_manager->getStorage('taxonomy_vocabulary');

  // Load the ILR in the News collection (id 4).
  $collection_in_the_news = $collection_storage->load(4);

  // Update the blog_26_categories term 'In the news' to 'ILR in the news'.
  $term_in_the_news = $term_storage->load(112);
  $term_in_the_news->name = 'ILR in the News';
  $term_in_the_news->path->alias = '/news/ilr-news';
  $term_in_the_news->save();

  // Create a COVID-19 term for blog_26_tags (news) if missing.
  $existing_covid19_terms = $term_storage->loadByProperties([
    'vid' => 'blog_26_tags',
    'name' => 'COVID-19',
  ]);

  if ($existing_covid19_terms) {
    $covid19_term = reset($existing_covid19_terms);
  }
  else {
    $covid19_term = $term_storage->create([
      'vid' => 'blog_26_tags',
      'name' => 'COVID-19',
    ]);
    $covid19_term->save();
  }

  // Get all the collection_items for collection 4 (ILR in the News).
  $collection_items = $collection_in_the_news->getItems();

  // For each collection 4 item, set it to collection 26 (news). Also set its
  // category to 112 (ILR in the News) and tag it with the COVID-19 term.
  foreach ($collection_items as $collection_item) {
    // This mainly filters out the categories and tags vocabularies. For some
    // unknown reason, the tags vocabulary collection item (23) is of the type
    // `blog` rather than `default`, so it actually has the field
    // `field_blog_categories`.
    if (!$collection_item->hasField('field_blog_categories') || $collection_item->id() == 23) {
      continue;
    }

    $collection_item->collection->target_id = 26;
    $collection_item->field_blog_categories->target_id = 112;
    $collection_item->field_blog_tags->target_id = $covid19_term->id();
    $collection_item->save();
  }

  // Delete the old ilr in the news collection and its vocabularies.
  foreach (['categories', 'tags'] as $type) {
    if ($vocab = $vocab_storage->load('blog_4_' . $type)) {
      $vocab->delete();
    }
  }
  $collection_in_the_news->delete();

  // Create a redirect from the old Covid-19 term to the new one.
  $redirect = $entity_type_manager->getStorage('redirect')->create([
    'status_code' => 301,
    'uid' => 1,
    'language' => 'en',
  ]);
  $redirect->setSource('news/ilr-news/covid-19');
  $redirect->setRedirect('/taxonomy/term/' . $covid19_term->id());
  $redirect->save();

  // Set the pathauto state for the "In the News" category.
  $term_in_the_news->path->pathauto = 1;
  $term_in_the_news->save();
}

/**
 * Convert the MAI collection from blog to subsite_blog.
 */
function ilr_post_update_mai_subsite_blog(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $vocabulary_storage = $entity_type_manager->getStorage('taxonomy_vocabulary');
  $collection_storage = $entity_type_manager->getStorage('collection');

  $vocabulary_storage->load('blog_37_categories')->delete();
  $vocabulary_storage->load('blog_37_tags')->delete();
  $collection_storage->load(37)->delete();

  $collection = $collection_storage->create([
    'cid' => 37,
    'type' => 'subsite_blog',
    'name' => 'Mobilizing Against Inequality',
  ]);
  $collection->save();
}

/**
 * Take over node/1 for the home page.
 */
function ilr_post_update_node_one_hostile_takeover(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $node_storage = $entity_type_manager->getStorage('node');

  // Node one is an automatically created instructor from salesforce. It'll get
  // re-created and we like our homepages at node/1!
  $node_storage->load(1)->delete();

  $homenode = $node_storage->create([
    'nid' => 1,
    'type' => 'page',
    'title' => 'Home',
  ]);
  $homenode->save();
}

/**
 * Create the homepage banner block.
 */
function ilr_post_update_homepage_banner(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $block_content_storage = $entity_type_manager->getStorage('block_content');

  $homeblock = $block_content_storage->create([
    'uuid' => '1d1640f5-d922-4622-835f-fba08eb0a788',
    'type' => 'simple_text',
    'info' => 'Homepage Banner',
  ]);
  $homeblock->save();
}

/**
 * Set a default count on post listings with no count.
 */
function ilr_post_update_update_post_listing_count(&$sandbox) {
  $query = \Drupal::entityQuery('paragraph');
  $query->condition('type', 'simple_collection_listing');
  $post_listing_paragraph_ids = $query->execute();
  $simple_post_listings = Paragraph::loadMultiple($post_listing_paragraph_ids);

  foreach ($simple_post_listings as $simple_post_listing) {
    $settings = $simple_post_listing->getAllBehaviorSettings();

    if (!$settings['post_listing']['count']) {
      $settings['post_listing']['count'] = 51;
      $simple_post_listing->setAllBehaviorSettings($settings);
      $simple_post_listing->save();
    }
  }
}

/**
 * Update dismissible and course message block content.
 */
function ilr_post_update_message_blocks(&$sandbox) {
  $entity_repository_service = \Drupal::service('entity.repository');
  $course_message_block = $entity_repository_service->loadEntityByUuid('block_content', '280c1d2d-0456-45eb-84dc-d114c5e7b2fa');
  $dismissible_block = $entity_repository_service->loadEntityByUuid('block_content', '8865eaf6-39b6-4672-bbce-4064fb057fca');

  $course_message_block->body->value = '<h2>Return to in-person instruction</h2><p>The ILR School will follow all required safety protocols in place at the time of each scheduled in-person session. If we are unable to deliver an in-person session due to safety concerns, we will offer a virtual alternative or reschedule the session for a later date. Our standard participant cancellation/refund policy will apply.</p>';
  $course_message_block->save();

  $dismissible_block->body->value = '<h2>Return to in-person instruction</h2><p>The ILR School will follow all required safety protocols in place at the time of each scheduled in-person session. If we are unable to deliver an in-person session due to safety concerns, we will offer a virtual alternative or reschedule the session for a later date. Our standard participant cancellation/refund policy will apply.</p>';
  $dismissible_block->save();
}

/**
 * Switch rich_text body field formatter from basic_formatting to
 * basic_formatting_with_media.
 */
function ilr_post_update_fix_rich_text_format(&$sandbox) {
  $paragraph_storage = \Drupal::service('entity_type.manager')->getStorage('paragraph');
  $query = $paragraph_storage->getQuery();
  $query->condition('type', 'rich_text',);
  $query->condition('field_body.format', 'basic_formatting');
  $relevant_paragraph_ids = $query->execute();
  $paragraphs = $paragraph_storage->loadMultiple($relevant_paragraph_ids);

  foreach ($paragraphs as $paragraph) {
    $paragraph->field_body->format = 'basic_formatting_with_media';
    $paragraph->save();
  }
}

/**
 * Move all the node references to collection_item references in curated post listings.
 */
function ilr_post_update_curated_post_listing_references(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $collection_item_storage = $entity_type_manager->getStorage('collection_item');
  $paragraph_storage = $entity_type_manager->getStorage('paragraph');
  $relevant_paragraph_ids = $paragraph_storage->getQuery()
    ->condition('type', 'curated_post_listing')
    ->execute();

  foreach ($paragraph_storage->loadMultiple($relevant_paragraph_ids) as $paragraph) {
    $remaining_posts = [];

    // For each post, get the canonical collection item.
    foreach ($paragraph->field_posts as $post) {
      $collection_item_ids = $collection_item_storage->getQuery()
        ->condition('canonical', 1)
        ->condition('item__target_type', 'node')
        ->condition('item__target_id', $post->target_id)
        ->execute();

      if ($collection_item_ids) {
        // Add this canonical collection item to field_items.
        $collection_item_id = reset($collection_item_ids);
        $paragraph->field_items[] = ['target_id' => $collection_item_id];
      }
      else {
        // If a canonical collection item is missing, the post is missing or
        // removed from a collection. Record the post nid here to leave in
        // field_posts.
        $remaining_posts[] = ['target_id' => $post->target_id];
      }
    }

    // Reset field_posts to only contain the posts that had no canonical
    // collection items.
    $paragraph->field_posts = $remaining_posts;

    // Save the paragraph.
    $paragraph->save();
  }
}

/**
 * Make the 'About ILR' collection bloggable.
 */
function ilr_post_update_add_post_support_to_about_ilr(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $collection_item_storage = $entity_type_manager->getStorage('collection_item');
  $about_ilr_collection = $entity_type_manager->getStorage('collection')->load(41);

  foreach (['categories', 'tags'] as $vocabulary_type) {
    $vocab = $entity_type_manager->getStorage('taxonomy_vocabulary')->create([
      'langcode' => 'en',
      'status' => TRUE,
      'name' => $about_ilr_collection->label() . ' ' . $vocabulary_type,
      'vid' => 'blog_' . $about_ilr_collection->id() . '_' . $vocabulary_type,
      'description' => 'Auto-generated vocabulary for ' . $about_ilr_collection->label() . ' blog',
    ]);
    $vocab->save();

    if ($vocab) {
      $entity_display_repository = \Drupal::service('entity_display.repository');

      // Add the vocab to this new collection.
      $collection_item_vocab = $collection_item_storage->create([
        'type' => 'default',
        'collection' => $about_ilr_collection->id(),
        'canonical' => TRUE,
        'weight' => 10,
      ]);

      $collection_item_vocab->item = $vocab;
      $collection_item_vocab->setAttribute('blog_taxonomy_' . $vocabulary_type, $vocab->id());
      $collection_item_vocab->save();

      // Configure each of the displays, based on type.
      if ($vocabulary_type === 'categories') {
        $category_form_display = $entity_display_repository->getFormDisplay('taxonomy_term', $vocab->id());

        // Configure the category fields and form display.
        foreach (CollectionEventSubscriber::getFieldConfiguration($vocab->id()) as $field_name => $field) {
          $new_field_config = $entity_type_manager->getStorage('field_config')->create($field['field_config']);
          $new_field_config->save();
          $category_form_display->setComponent($field_name, $field['form_display']);
        }

        $category_form_display->removeComponent('description');
        $category_form_display->save();

        // Configure the category view display layout builder sections.
        $category_view_display = $entity_display_repository->getViewDisplay('taxonomy_term', $vocab->id());
        $category_view_display->enableLayoutBuilder();
        $category_view_display->save();
        $category_view_display->removeAllSections();

        foreach (CollectionEventSubscriber::getLayoutSections($vocab, 'category') as $section) {
          $category_view_display->appendSection($section);
        }

        $category_view_display->save();

        // Add the "ILR Stories" category.
        $ilr_stories_category = $entity_type_manager->getStorage('taxonomy_term')->create([
          'vid' => $vocab->id(),
          'name' => 'ILR Stories',
        ]);
        $ilr_stories_category->save();

        $canonical_collection_items = $collection_item_storage->loadByProperties([
          'canonical' => 1,
          'collection' => 12,
        ]);

        foreach ($canonical_collection_items as $collection_item) {
          if (!$collection_item->item->entity instanceof NodeInterface) {
            continue;
          }

          $cross_post = $collection_item_storage->create([
            'type' => 'blog',
            'collection' => 41,
            'item' => $collection_item->item->entity,
            'canonical' => FALSE,
            'field_blog_categories' => ['target_id' => $ilr_stories_category->id()],
          ]);
          $cross_post->save();
        }
      }
      else {
        $tags_form_display = $entity_display_repository->getFormDisplay('taxonomy_term', $vocab->id());

        // Configure the tags fields and form display.
        foreach (CollectionEventSubscriber::getFieldConfiguration($vocab->id()) as $field_name => $field) {
          $new_field_config = $entity_type_manager->getStorage('field_config')->create($field['field_config']);
          $new_field_config->save();
          $tags_form_display->setComponent($field_name, $field['form_display']);
        }

        $tags_form_display->removeComponent('description');
        $tags_form_display->save();

        // Configure the tags view display layout builder sections.
        $tags_view_display = $entity_display_repository->getViewDisplay('taxonomy_term', $vocab->id());
        $tags_view_display->enableLayoutBuilder();
        $tags_view_display->save();
        $tags_view_display->removeAllSections();

        foreach (CollectionEventSubscriber::getLayoutSections($vocab, 'tag') as $section) {
          $tags_view_display->appendSection($section);
        }

        $tags_view_display->save();
      }
    }
  }
}

/**
 * Un-stick all posts and media mentions.
 */
function ilr_post_update_unstick_posts(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $sticky_post_nodes = $entity_type_manager->getStorage('node')->loadByProperties([
    'type' => ['post', 'media_mention'],
    'sticky' => 1,
  ]);

  foreach ($sticky_post_nodes as $node) {
    $node->sticky = 0;
    $node->save();
  }
}

/**
 * Fix aliases and redirects for nodes saved after removing the sticky bit.
 */
function ilr_post_update_fix_unstickied_node_aliases(&$sandbox) {
  $pathauto_generator = \Drupal::service('pathauto.generator');
  $alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
  $node_storage = \Drupal::entityTypeManager()->getStorage('node');
  $nodes = [];

  $query = $alias_storage->getQuery();
  $result = $query->condition('alias', '/taxonomy/term/%', 'LIKE')
    ->condition('path', '/node/%', 'LIKE')
    ->execute();

  foreach ($alias_storage->loadMultiple($result) as $alias) {
    $nodes[] = Url::fromUri("internal:" . $alias->path->value)->getRouteParameters()['node'];
  }

  foreach ($node_storage->loadMultiple($nodes) as $node) {
    $result = $pathauto_generator->updateEntityAlias($node, 'update');
  }
}

/**
 * Update hard-coded icons in rich text paragraphs.
 */
function ilr_post_update_fix_rich_text_icons(&$sandbox) {
  $paragraph_storage = \Drupal::service('entity_type.manager')->getStorage('paragraph');
  $query = $paragraph_storage->getQuery();
  $query->condition('type', 'promo');
  $query->condition('field_body', '%/libraries/union/source/images/%', 'LIKE');
  $relevant_paragraph_ids = $query->execute();
  $paragraphs = $paragraph_storage->loadMultiple($relevant_paragraph_ids);

  foreach ($paragraphs as $paragraph) {
    $paragraph->field_body->value = str_replace('/libraries/union/source/images/', '/sites/default/files-d8/union/images/', $paragraph->field_body->value);
    $paragraph->save();
  }
}

/**
 * Update social footer block.
 */
function ilr_post_update_update_social_footer_block_icons(&$sandbox) {
  $block = \Drupal::service('entity.repository')->loadEntityByUuid('block_content', '48bd16f4-0fe8-4b1a-800b-089c03c0be23');
  $block->body->value = '<div class="social-follow">
    <ul class="social-follow__items">
      <li class="social-follow__item"><a class="social-follow__link" href="https://www.linkedin.com/company/cornell-university-ilr-school"><svg class="cu-icon__image" viewbox="0 0 100 100" width="1.8em" xmlns="http://www.w3.org/2000/svg">
      <title></title>
      <use href="/sites/default/files-d8/union/images/icons.svg#linkedin"></use></svg></a></li>
      <li class="social-follow__item"><a class="social-follow__link" href="https://facebook.com/ilrschool"><svg class="cu-icon__image" viewbox="0 0 100 100" width="1.4em" xmlns="http://www.w3.org/2000/svg">
      <title></title>
      <use href="/sites/default/files-d8/union/images/icons.svg#facebook"></use></svg></a></li>
      <li class="social-follow__item"><a class="social-follow__link" href="https://twitter.com/CornellILR"><svg class="cu-icon__image" viewbox="0 0 100 100" width="2em" xmlns="http://www.w3.org/2000/svg">
      <title></title>
      <use href="/sites/default/files-d8/union/images/icons.svg#twitter"></use></svg></a></li>
      <li class="social-follow__item"><a class="social-follow__link" href="https://instagram.com/cornellilr"><svg class="cu-icon__image" viewbox="0 0 100 100" width="1em" xmlns="http://www.w3.org/2000/svg">
      <title></title>
      <use href="/sites/default/files-d8/union/images/icons.svg#instagram"></use></svg></a></li>
      <li class="social-follow__item"><a class="social-follow__link" href="https://www.youtube.com/user/CornellUniversityILR?sub_confirmation=1"><svg class="cu-icon__image" viewbox="0 0 100 100" width="1.5em" xmlns="http://www.w3.org/2000/svg">
      <title></title>
      <use href="/sites/default/files-d8/union/images/icons.svg#youtube"></use></svg></a></li>
    </ul>
  </div>';
  $block->save();
}

/**
 * Set layout for all legacy promo cards.
 */
function ilr_post_update_set_promo_layouts(&$sandbox) {
  $query = \Drupal::entityQuery('paragraph');
  $query->condition('type', 'promo');
  $promo_paragraph_ids = $query->execute();
  $promo_paragraphs = Paragraph::loadMultiple($promo_paragraph_ids);

  foreach ($promo_paragraphs as $promo_paragraph) {
    $settings = $promo_paragraph->getAllBehaviorSettings();
    $settings['ilr_card']['layout'] = 'promo';
    $promo_paragraph->setAllBehaviorSettings($settings);
    $promo_paragraph->save();
  }
}

/**
 * Split single name field in to first and last for inquiry forms.
 */
function ilr_post_update_inquiry_form_name_updates(&$sandbox) {
  $submission_storage = \Drupal::entityTypeManager()->getStorage('webform_submission');
  $submission_ids = \Drupal::entityQuery('webform_submission')
    ->accessCheck(FALSE)
    ->condition('webform_id', ['program_inquiry_form_hr_leadersh', 'program_inquiry_form_cdo_program', 'program_inquiry_form'], 'IN')
    ->sort('sid')
    ->execute();

  $submissions = $submission_storage->loadMultiple($submission_ids);

  /** @var \Drupal\webform\Entity\WebformSubmission $submission */
  foreach ($submissions as $submission) {
    $data = $submission->getData();

    if (empty($data['name'])) {
      continue;
    }

    $name_arr = explode(' ', trim(str_replace(['Dr.', 'PhD', ','], '', $data['name'])));

    if (count($name_arr) > 5) {
      continue;
    }

    if (count($name_arr) === 1) {
      $name_arr[] = 'NOT PROVIDED';
    }

    $data += [
      'firstname' => reset($name_arr),
      'lastname' => end($name_arr),
    ];

    $submission->setData($data);
    $submission->save();
    salesforce_push_entity_crud($submission, 'push_create');
  }
}

/**
 * Add a placeholder card for the cyber monday block.
 */
function ilr_post_update_add_cyber_monday_placeholder(&$sandbox) {
  $blockEntityManager = \Drupal::service('entity_type.manager')->getStorage('block_content');

  $block = $blockEntityManager->create([
    'type' => 'component',
    'uuid' => 'ac0e6d2c-79ce-4513-800e-1aae2f627b15',
    'info' => 'CyberMonday Discount',
    'label_display' => 0,
  ]);

  $block->save();
}

/**
 * Update webform paragraphs to new field type.
 */
function ilr_post_update_webform_component_type(&$sandbox) {
  $paragraph_storage = \Drupal::entityTypeManager()->getStorage('paragraph');
  $webform_paragraphs = $paragraph_storage->loadByProperties(['type' => 'form']);

  foreach ($webform_paragraphs as $paragraph) {
    $paragraph->field_web_form->target_id = $paragraph->field_webform->target_id;
    $paragraph->field_web_form->status = 'open';
    $paragraph->save();
  }
}

/**
 * Update section paragraphs to set frame to none if heading is empty.
 */
function ilr_post_update_section_frame_setting(&$sandbox) {
  $paragraph_storage = \Drupal::entityTypeManager()->getStorage('paragraph');
  $section_paragraphs = $paragraph_storage->loadByProperties(['type' => 'section']);

  /** @var \Drupal\paragraphs\ParagraphInterface */
  foreach ($section_paragraphs as $paragraph) {
    if ($paragraph->field_heading->isEmpty()) {
      $behavior_settings = $paragraph->getAllBehaviorSettings();
      unset($behavior_settings['union_section_settings']['frame_position']);
      $paragraph->setAllBehaviorSettings($behavior_settings);
      $paragraph->save();
    }
  }
}

/**
 * Update content block with sharp spring tracking code.
 */
function ilr_post_update_sharp_spring_block(&$sandbox) {
  $block = \Drupal::service('entity.repository')->loadEntityByUuid('block_content', 'e08e0f4c-7cbf-4941-96b4-95e8d9044181');

  if ($block) {
    $block->info = 'Sharp Spring tracker';
    $block->body->value = <<<EOT
    <script type="text/javascript">
        var _ss = _ss || [];
        _ss.push(['_setDomain', 'https://koi-3QS88UKVPW.marketingautomation.services/net']);
        _ss.push(['_setAccount', 'KOI-4M212EIGMA']);
        _ss.push(['_trackPageView']);
        window._pa = window._pa || {};
    (function() {
        var ss = document.createElement('script');
        ss.type = 'text/javascript'; ss.async = true;
        ss.src = ('https:' == document.location.protocol ? 'https://' : 'http://') + 'koi-3QS88UKVPW.marketingautomation.services/client/ss.js?ver=2.4.0';
        var scr = document.getElementsByTagName('script')[0];
        scr.parentNode.insertBefore(ss, scr);
    })();
    </script>
    EOT;
    $block->body->format = 'full_html';
    $block->save();
  }
}

/**
 * Update content block with sharp spring forms code.
 */
function ilr_post_update_sharp_spring_forms_block(&$sandbox) {
  $block = \Drupal::service('entity.repository')->loadEntityByUuid('block_content', '6b0e39b6-af42-4710-b20b-7833cdb4e945');

  if ($block) {
    $block->info = 'Sharp Spring tracking for forms';
    $block->body->value = <<<EOT
    <script type="text/javascript">
      var __ss_noform = __ss_noform || [];
      __ss_noform.push(['baseURI', 'https://app-3QS88UKVPW.marketingautomation.services/webforms/receivePostback/MzawMDc3sTQ0AgA/']);
      __ss_noform.push(['endpoint', '203707bd-7fff-4b4e-a7c1-753baa540e2c']);
    </script><script type="text/javascript" src=https://koi-3QS88UKVPW.marketingautomation.services/client/noform.js?ver=1.24 ></script>
    EOT;
    $block->body->format = 'full_html';
    $block->save();
  }
}

/**
 * Update section paragraphs to set id fragment from in-page title.
 */
function ilr_post_update_section_in_page_fragment_setting(&$sandbox) {
  $paragraph_storage = \Drupal::entityTypeManager()->getStorage('paragraph');
  $section_paragraphs = $paragraph_storage->loadByProperties(['type' => 'section']);

  /** @var \Drupal\paragraphs\ParagraphInterface */
  foreach ($section_paragraphs as $paragraph) {
    $behavior_settings = $paragraph->getAllBehaviorSettings();

    if ($title_value = $behavior_settings['in_page_nav']['title'] ?? FALSE) {
      $css_cleaned_fragment = Html::cleanCssIdentifier(strtolower($title_value));
      $behavior_settings['in_page_nav']['fragment'] = $css_cleaned_fragment;
      $paragraph->setAllBehaviorSettings($behavior_settings);
      $paragraph->save();
    }
  }
}

/**
 * Update pathauto aliases for Climate Jobs collection and related blog.
 */
function ilr_post_update_update_climate_jobs_aliases(&$sandbox) {
  $pathauto_generator = \Drupal::service('pathauto.generator');
  $collection_storage = \Drupal::entityTypeManager()->getStorage('collection');
  $collection_item_storage = \Drupal::entityTypeManager()->getStorage('collection_item');

  foreach ($collection_storage->loadMultiple([23, 30]) as $climate_jobs_collection) {
    // Update the alias for the collections themselves before items.
    $climate_jobs_collection->path->alias = str_replace('/labor-leading-climate', '/climate-jobs-institute', $climate_jobs_collection->path->alias);
    $climate_jobs_collection->save();

    // Load all items, sorted by target_type to get terms first.
    $collection_item_ids = \Drupal::entityQuery('collection_item')
      ->condition('collection', $climate_jobs_collection->id())
      ->sort('item__target_type', 'DESC')
      ->execute();

    foreach ($collection_item_storage->loadMultiple($collection_item_ids) as $collection_item) {
      if (!$collection_item->item->entity instanceof ContentEntityInterface) {
        continue;
      }

      if ($collection_item->isCanonical()) {
        $pathauto_generator->updateEntityAlias($collection_item->item->entity, 'update', ['force' => TRUE]);
      }
      else {
        $pathauto_generator->updateEntityAlias($collection_item, 'update', ['force' => TRUE]);
      }
    }
  }
}

/**
 * Fix pathauto aliases for Climate Jobs collection and related blog.
 */
function ilr_post_update_fix_mistaken_climate_jobs_aliases(&$sandbox) {
  $pathauto_generator = \Drupal::service('pathauto.generator');
  $collection_storage = \Drupal::entityTypeManager()->getStorage('collection');
  $collection_item_storage = \Drupal::entityTypeManager()->getStorage('collection_item');

  foreach ($collection_storage->loadMultiple([23, 30]) as $climate_jobs_collection) {
    // Update the alias for the collections themselves before items.
    $climate_jobs_collection->path->alias = str_replace('/worker-institute/climate-jobs-institute', '/climate-jobs-institute', $climate_jobs_collection->path->alias);
    $climate_jobs_collection->save();

    // Load all items, sorted by target_type to get terms first.
    $collection_item_ids = \Drupal::entityQuery('collection_item')
      ->condition('collection', $climate_jobs_collection->id())
      ->sort('item__target_type', 'DESC')
      ->execute();

    foreach ($collection_item_storage->loadMultiple($collection_item_ids) as $collection_item) {
      if (!$collection_item->item->entity instanceof ContentEntityInterface) {
        continue;
      }

      if ($collection_item->isCanonical()) {
        $pathauto_generator->updateEntityAlias($collection_item->item->entity, 'update', ['force' => TRUE]);
      }
      else {
        $pathauto_generator->updateEntityAlias($collection_item, 'update', ['force' => TRUE]);
      }
    }
  }
}

/**
 * Move card bg_color settings to color_scheme and set default scheme.
 */
function ilr_post_update_set_card_color_schemes(&$sandbox) {
  $query = \Drupal::entityQuery('paragraph');
  $query->condition('type', ['promo', 'topic_list'], 'IN');
  $promo_paragraph_ids = $query->execute();
  $cards = Paragraph::loadMultiple($promo_paragraph_ids);

  foreach ($cards as $card) {
    $settings = $card->getAllBehaviorSettings();
    if (isset($settings['ilr_card']['bg_color'])) {
      $settings['ilr_color']['color_scheme'] =  $settings['ilr_card']['bg_color'];
      unset($settings['ilr_card']['bg_color']);
    }
    else {
      $settings['ilr_color']['color_scheme'] =  'light';
    }

    $card->setAllBehaviorSettings($settings);
    $card->save();
  }
}

/**
 * Move card and section icon settings to new icon setting behavior.
 */
function ilr_post_update_set_icons(&$sandbox) {
  $query = \Drupal::entityQuery('paragraph');
  $query->condition('type', ['promo', 'section'], 'IN');
  $paragraph_ids = $query->execute();
  $paragraphs = Paragraph::loadMultiple($paragraph_ids);

  foreach ($paragraphs as $paragraph) {
    $settings = $paragraph->getAllBehaviorSettings();

    foreach (['icon', 'icon_label', 'icon_placement'] as $prop) {
      if (isset($settings['ilr_card'][$prop])) {
        $settings['ilr_icon'][$prop] = $settings['ilr_card'][$prop];
        unset($settings['ilr_card'][$prop]);
      }

      if (isset($settings['union_section_settings'][$prop])) {
        $settings['ilr_icon'][$prop] = $settings['union_section_settings'][$prop];
        unset($settings['union_section_settings'][$prop]);
      }
    }

    $paragraph->setAllBehaviorSettings($settings);
    $paragraph->save();
  }
}

/**
 * Add bundle fields to event landing page node type.
 */
function ilr_post_update_event_landing_bundle_fields() {
  $entity_type = \Drupal::entityTypeManager()->getDefinition('node');
  $field_definition_listener = \Drupal::service('field_definition.listener');

  foreach (EventNodeBase::bundleFieldDefinitions($entity_type, 'event_landing_page', []) as $field_name => $storage_definition) {
    \Drupal::entityDefinitionUpdateManager()->installFieldStorageDefinition($field_name, 'node', 'node', $storage_definition);
  }

  // Add the new fields to fields to entity.definitions.bundle_field_map. In my
  // testing, this needs to happen after the fields are installed above.
  // @see https://www.drupal.org/i/3045509
  foreach (EventNodeBase::bundleFieldDefinitions($entity_type, 'event_landing_page', []) as $field_name => $storage_definition) {
    $field_definition_listener->onFieldDefinitionCreate($storage_definition);
  }
}

/**
 * Remove CAHRS class nodes and mapped objects.
 */
function ilr_post_update_update_cahrs_entities(&$sandbox) {
  $sf_cahrs_class_ids = [
    'a0i4U00000Tc0baQAB',
    'a0i4U00000Tc0bbQAB',
    'a0i4U00000Tc0bcQAB',
    'a0i4U00000Tc0bdQAB',
    'a0i4U00000Tc0beQAB',
    'a0i4U00000Tc0bfQAB',
    'a0i4U00000Tc0c6QAB',
    'a0i4U00000Tc0gkQAB',
    'a0i4U00000Tc0hEQAR',
    'a0i4U00000Tc0hrQAB',
    'a0i4U00000Tc0hsQAB',
    'a0i4U00000Tc0htQAB',
    'a0i4U00000Tc0huQAB',
    'a0i4U00000Tc0hvQAB',
    'a0i4U00000Tc0hwQAB',
    'a0i4U00000Tc0hxQAB',
    'a0i4U00000Tc0hyQAB',
    'a0i4U00000Tc0i1QAB',
    'a0i4U00000Tc0i3QAB',
    'a0i4U00000Tc0i5QAB',
    'a0i4U00000Tc0i6QAB',
    'a0i4U00000Tc0i7QAB',
    'a0i4U00000Tc0i8QAB',
    'a0i4U00000Tc0i9QAB',
    'a0i4U00000Tc0jhQAB',
    'a0i4U00000Tc0bgQAB',
    'a0i4U00000Tc0cBQAR',
    'a0i4U00000Tc0cGQAR',
    'a0i4U00000Tc0cHQAR',
    'a0i4U00000Tc0cIQAR',
    'a0i4U00000Tc0cJQAR',
    'a0i4U00000Tc0cZQAR',
    'a0i4U00000Tc0glQAB',
    'a0i4U00000Tc0hFQAR',
    'a0i4U00000Tc0iBQAR',
    'a0i4U00000Tc0iCQAR',
    'a0i4U00000Tc0iDQAR',
    'a0i4U00000Tc0iEQAR',
    'a0i4U00000Tc0iGQAR',
    'a0i4U00000Tc0iHQAR',
    'a0i4U00000Tc0iIQAR',
    'a0i4U00000Tc0iJQAR',
    'a0i4U00000Tc0iKQAR',
    'a0i4U00000Tc0iLQAR',
    'a0i4U00000Tc0iOQAR',
    'a0i4U00000Tc0iQQAR',
    'a0i4U00000Tc0iSQAR',
    'a0i4U00000Tc0iUQAR',
    'a0i4U00000Tc0iVQAR',
    'a0i4U00000Tc0iWQAR',
    'a0i4U00000Tc0YVQAZ',
    'a0i4U00000Tc0cKQAR',
    'a0i4U00000Tc0cLQAR',
    'a0i4U00000Tc0cMQAR',
    'a0i4U00000Tc0cOQAR',
    'a0i4U00000Tc0fEQAR',
    'a0i4U00000Tc0fFQAR',
    'a0i4U00000Tc0fGQAR',
    'a0i4U00000Tc0fIQAR',
    'a0i4U00000Tc0fJQAR',
    'a0i4U00000Tc0fKQAR',
    'a0i4U00000Tc0gmQAB',
    'a0i4U00000Tc0iXQAR',
    'a0i4U00000Tc0iaQAB',
    'a0i4U00000Tc0icQAB',
    'a0i4U00000Tc0ieQAB',
    'a0i4U00000Tc0ifQAB',
    'a0i4U00000Tc0igQAB',
    'a0i4U00000Tc0ilQAB',
    'a0i4U00000Tc0ioQAB',
    'a0i4U00000Tc0ipQAB',
    'a0i4U00000Tc0iqQAB',
    'a0i4U00000Tc0irQAB',
    'a0i4U00000Tc0isQAB',
    'a0i4U00000Tc0itQAB',
    'a0i4U00000Tc0ivQAB',
    'a0i4U00000Tc0iwQAB',
    'a0i4U00000Tc0ixQAB',
    'a0i4U00000Tc0iyQAB',
    'a0i4U00000Tc0jiQAB',
    'a0i4U00000Tc0YfQAJ',
    'a0i4U00000Tc0YpQAJ',
    'a0i4U00000Tc0YqQAJ',
    'a0i4U00000Tc0YrQAJ',
    'a0i4U00000Tc0YsQAJ',
    'a0i4U00000Tc0YtQAJ',
    'a0i4U00000Tc0bhQAB',
    'a0i4U00000Tc0biQAB',
    'a0i4U00000Tc0fLQAR',
    'a0i4U00000Tc0fMQAR',
    'a0i4U00000Tc0fNQAR',
    'a0i4U00000Tc0fOQAR',
    'a0i4U00000Tc0fPQAR',
    'a0i4U00000Tc0fQQAR',
    'a0i4U00000Tc0fRQAR',
    'a0i4U00000Tc0fSQAR',
    'a0i4U00000Tc0fTQAR',
    'a0i4U00000Tc0gnQAB',
    'a0i4U00000Tc0hGQAR',
    'a0i4U00000Tc0hHQAR',
    'a0i4U00000Tc0izQAB',
    'a0i4U00000Tc0j0QAB',
    'a0i4U00000Tc0j1QAB',
    'a0i4U00000Tc0j2QAB',
    'a0i4U00000Tc0j3QAB',
    'a0i4U00000Tc0j4QAB',
    'a0i4U00000Tc0j5QAB',
    'a0i4U00000Tc0j6QAB',
    'a0i4U00000Tc0j7QAB',
    'a0i4U00000Tc0j8QAB',
    'a0i4U00000Tc0j9QAB',
    'a0i4U00000Tc0jAQAR',
    'a0i4U00000Tc0jCQAR',
    'a0i4U00000Tc0YuQAJ',
    'a0i4U00000Tc0fUQAR',
    'a0i4U00000Tc0faQAB',
    'a0i4U00000Tc0fbQAB',
    'a0i4U00000Tc0fcQAB',
    'a0i4U00000Tc0fdQAB',
    'a0i4U00000Tc0feQAB',
    'a0i4U00000Tc0ffQAB',
    'a0i4U00000Tc0goQAB',
    'a0i4U00000Tc0hIQAR',
    'a0i4U00000Tc0jEQAR',
    'a0i4U00000Tc0jFQAR',
    'a0i4U00000Tc0jGQAR',
    'a0i4U00000Tc0jHQAR',
    'a0i4U00000Tc0jIQAR',
    'a0i4U00000Tc0jJQAR',
    'a0i4U00000Tc0jKQAR',
    'a0i4U00000Tc0jLQAR',
    'a0i4U00000Tc0jMQAR',
    'a0i4U00000Tc0jNQAR',
    'a0i4U00000Tc0jOQAR',
    'a0i4U00000Tc0jPQAR',
    'a0i4U00000Tc0jSQAR',
    'a0i4U00000Tc0jUQAR',
    'a0i4U00000Tc0fgQAB',
    'a0i4U00000Tc0fhQAB',
    'a0i4U00000Tc0fiQAB',
    'a0i4U00000Tc0fjQAB',
    'a0i4U00000Tc0gpQAB',
    'a0i4U00000Tc0hJQAR',
    'a0i4U00000Tc0jVQAR',
    'a0i4U00000Tc0jXQAR',
    'a0i4U00000Tc0jYQAR',
    'a0i4U00000Tc0jZQAR',
    'a0i4U00000Tc0jaQAB',
    'a0i4U00000Tc0jbQAB',
    'a0i4U00000Tc0jcQAB',
    'a0i4U00000Tc0jdQAB',
    'a0i4U00000Tc0jeQAB',
    'a0i4U00000Tc0jgQAB',
  ];
  $query = \Drupal::entityQuery('salesforce_mapped_object');
  $query->condition('salesforce_id', $sf_cahrs_class_ids, 'IN');
  $mapped_cahrs_class_ids = $query->execute();
  $class_nids_to_delete = [];

  foreach (MappedObject::loadMultiple($mapped_cahrs_class_ids) as $class_mapping) {
    $entity_id = $class_mapping->getMappedEntity()->id();
    $class_nids_to_delete[$entity_id] = $entity_id;
  }

  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $class_nodes_to_delete = $storage->loadMultiple(array_keys($class_nids_to_delete));
  $storage->delete($class_nodes_to_delete);
}

/**
 * Add initial event_keywords vocabulary terms.
 */
function ilr_post_update_add_event_keywords_terms_and_migrate_event_listing_behaviors(&$sandbox) {
  $entity_type_manager = \Drupal::entityTypeManager();
  $term_storage = $entity_type_manager->getStorage('taxonomy_term');
  $paragraph_storage = $entity_type_manager->getStorage('paragraph');
  $term_names_to_tid = [];

  $terms = [
    'ILR Alumni',
    'WI',
    'Scheinman Institute',
    'ILR Student Events',
    'ILR',
    'ILR School',
  ];

  foreach ($terms as $term_name) {
    $term = $term_storage->create([
      'vid' => 'event_keywords',
      'name' => $term_name,
    ]);
    $term->save();
    $term_names_to_tid[strtolower($term_name)] = $term->id();
  }

  $paragraph_ids = $paragraph_storage
    ->getQuery()
    ->condition('type', 'event_listing')
    ->execute();
  $paragraphs = $entity_type_manager->getStorage('paragraph')->loadMultiple($paragraph_ids);

  /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
  foreach ($paragraphs as $paragraph) {
    $settings = $paragraph->getAllBehaviorSettings();
    $settings['ilr_event_listing'] = $settings['localist_events'];
    unset($settings['localist_events']);

    $settings['ilr_event_listing']['sources'] = [ '_localist' => '_localist' ];

    $keywords = explode(',', $settings['ilr_event_listing']['keywords']);
    $settings['ilr_event_listing']['keywords'] = [];

    foreach ($keywords as $key => $keyword) {
      $tid = $term_names_to_tid[strtolower(trim($keyword))];
      $settings['ilr_event_listing']['keywords'][$tid] = $keyword;
    }

    $paragraph->setAllBehaviorSettings($settings);
    $paragraph->save();
  }
}

/**
 * Add bundle fields to certificates node type.
 */
function ilr_post_update_certificate_bundle_fields() {
  $entity_type = \Drupal::entityTypeManager()->getDefinition('node');
  $field_definition_listener = \Drupal::service('field_definition.listener');

  // Add the new fields to fields to entity.definitions.bundle_field_map. In
  // this case, the field(s) are computed, so the storage doesn't need to be
  // installed.
  // @see https://www.drupal.org/i/3045509
  foreach (CertificateNode::bundleFieldDefinitions($entity_type, 'certificate', []) as $field_name => $storage_definition) {
    $field_definition_listener->onFieldDefinitionCreate($storage_definition);
  }
}

/**
 * Add certificate_node sf mappings for existing certificates.
 */
function ilr_post_update_add_certificate_mappings() {
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $mapped_object_storage = $entity_type_manager->getStorage('salesforce_mapped_object');
  $node_storage = $entity_type_manager->getStorage('node');

  $items = [
    '3964' => 'a0n0P00000DHOF9QAP',
    '3965' => 'a0n0P00000DHOEaQAP',
    '3966' => 'a0n0P00000DHOFkQAP',
    '3967' => 'a0n0P00000DHOCPQA5',
    '3968' => 'a0n0P00000DHOC4QAP',
    '3969' => 'a0n0P00000DHOB1QAP',
    '3970' => 'a0n0P00000DW1pMQAT',
    '3973' => 'a0n4U00000FnxbTQAR',
    '3974' => 'a0n4U00000FnxazQAB',
    '3976' => 'a0n0P00000E39aCQAR',
    '3977' => 'a0n0P00000DHOL0QAP',
    '3978' => 'a0n0P00000DHOLDQA5',
    '3979' => 'a0n0P00000DHOGmQAP',
    // '3981' => 'a0n0P00000DHOEaQAP', // Dupe
    '3982' => 'a0n0P00000DV8uwQAD',
    '3985' => 'a0n4U00000EwUiDQAV',
    '5482' => 'a0n4U00000EwMwbQAF',
    '5483' => 'a0n4U00000FGnfLQAT',
    '10092' => 'a0n4U00000GRBHIQA5',
  ];

  foreach ($items as $nid => $sfid) {
    if (!$node = $node_storage->load($nid)) {
      continue;
    }

    $mapped_object = $mapped_object_storage->create([
      'salesforce_mapping' => 'certificate_node',
      'salesforce_id' => $sfid,
    ]);

    $mapped_object->drupal_entity = $node;
    $mapped_object->save();
  }
}

/**
 * Update all existing post listings to move the collection value to a setting and update term settings.
 */
function ilr_post_update_update_post_listing_collection_setting(&$sandbox) {
  $query = \Drupal::entityQuery('paragraph');
  $query->condition('type', 'simple_collection_listing');
  $post_listing_paragraph_ids = $query->execute();
  $simple_post_listings = Paragraph::loadMultiple($post_listing_paragraph_ids);

  foreach ($simple_post_listings as $simple_post_listing) {
    $settings = $simple_post_listing->getAllBehaviorSettings();
    $settings['post_listing']['collection'] = $simple_post_listing->field_collection->target_id;

    if (isset($settings['post_listing']['post_categories'])) {
      $settings['post_listing']['blog_terms']['post_categories'] = $settings['post_listing']['post_categories'];
      unset($settings['post_listing']['post_categories']);
    }

    if (isset($settings['post_listing']['post_tags'])) {
      $settings['post_listing']['blog_terms']['post_tags'] = $settings['post_listing']['post_tags'];
      unset($settings['post_listing']['post_tags']);
    }

    $simple_post_listing->setAllBehaviorSettings($settings);
    $simple_post_listing->save();
  }
}

/**
 * Create SF leads for GLI (ncp) newsletter submissions.
 */
function ilr_post_update_create_ncp_subscription_leads(&$sandbox) {
  $submission_storage = \Drupal::entityTypeManager()->getStorage('webform_submission');
  $submission_ids = \Drupal::entityQuery('webform_submission')
    ->accessCheck(FALSE)
    ->condition('webform_id', 'ncp_subscription')
    ->sort('sid')
    ->execute();

  $submissions = $submission_storage->loadMultiple($submission_ids);

  /** @var \Drupal\webform\Entity\WebformSubmission $submission */
  foreach ($submissions as $submission) {
    salesforce_push_entity_crud($submission, 'push_create');
  }
}
