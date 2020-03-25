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
