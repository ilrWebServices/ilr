<?php

namespace Drupal\ilr_content_section_import;

/**
 * Methods for running the section nid link importer in a batch.
 *
 * @see \Drupal\ilr_content_section_import\Form\ContentSectionImportForm::submitForm()
 */
class SectionNidLinkBatch {

  /**
   * Processes the imported section content to replace old nids with new nids.
   *
   * @param array $row
   *   The a row of data to import as a node.
   * @param array $context
   *   The batch context.
   */
  public static function process($row, &$context) {
    $entity_type_manager = \Drupal::entityTypeManager();
    $node_storage = $entity_type_manager->getStorage('node');
    $import_mapped_object_storage = $entity_type_manager->getStorage('section_import_mapped_object');

    // Load the node that was previously created for this row.
    $node_mapping = $import_mapped_object_storage->loadByProperties([
      'type' => 'node',
      'sourceid' => $row->nid,
    ]);

    if (empty($node_mapping)) {
      return;
    }

    $node_mapping = reset($node_mapping);

    // Load the node.
    $node = $node_storage->load((int) $node_mapping->destid->value);

    if (empty($node)) {
      return;
    }

    foreach ($node->field_sections as $section) {
      foreach ($section->entity->field_components as $paragraph) {
        if ($paragraph->entity->bundle() !== 'rich_text') {
          return;
        }

        $text_content = $paragraph->entity->field_body->value;

        if (preg_match_all('/href="\/node\/(\d+)"/m', $text_content, $matches, PREG_SET_ORDER)) {
          foreach ($matches as $match) {
            $new_node_mapping = $import_mapped_object_storage->loadByProperties([
              'type' => 'node',
              'sourceid' => $match[1],
            ]);

            if (empty($new_node_mapping)) {
              continue;
            }

            $new_node_mapping = reset($new_node_mapping);
            $new_nid = $new_node_mapping->destid->value;
            $new_node = $node_storage->load((int) $new_nid);

            if (!$new_node) {
              continue;
            }

            $text_content = str_replace($match[0], sprintf('data-entity-substitution="canonical" data-entity-type="node" data-entity-uuid="%s" href="/node/' . $new_nid . '"', $new_node->uuid()), $text_content);

            // Update the body to the replaced value.
            $paragraph->entity->field_body->value = $text_content;
          }
        }

        $paragraph->entity->save();
      }
    }
  }

  /**
   * Finish batch.
   *
   * @param bool $success
   *   Indicate that the batch API tasks were all completed successfully.
   * @param array $results
   *   An array of all the results that were updated in update_do_one().
   * @param array $operations
   *   A list of the operations that had not been completed by the batch API.
   */
  public static function finish($success, $results, $operations) {
    $messenger = \Drupal::messenger();
    if ($success) {
      $messenger->addMessage(t('Content link update complete.'));

      if (!empty($results['errors'])) {
        $logger = \Drupal::logger('ilr');
        foreach ($results['errors'] as $error) {
          $logger->error($error);
        }
      }
    }
  }

}
