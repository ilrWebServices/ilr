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
