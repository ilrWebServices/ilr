<?php

namespace Drupal\ilr_content_section_import;

use Drupal\collection\Entity\Collection;
use Drupal\Core\Batch\BatchBuilder;

/**
 * Methods for running the section importer in a batch.
 *
 * @see \Drupal\ilr_content_section_import\Form\ContentSectionImportForm::submitForm()
 */
class SectionImportBatch {
  /**
   * Processes the section import batch and persists the importer.
   *
   * @param array $row
   *   The a row of data to import as a node.
   * @param array $context
   *   The batch context.
   */
  public static function process($row, Collection $content_section, &$context) {
    if (!isset($context['sandbox']['content_section'])) {
      $context['sandbox']['content_section'] = $content_section;
    }

    $content_section = $context['sandbox']['content_section'];
    $entity_type_manager = \Drupal::entityTypeManager();
    $node_storage = $entity_type_manager->getStorage('node');
    $paragraph_storage = $entity_type_manager->getStorage('paragraph');
    $import_mapped_object_storage = $entity_type_manager->getStorage('section_import_mapped_object');

    // TODO If this node has been imported before, load it.

    // If this node has not been imported before, create a new node.
    $node_imported = $node_storage->create([
      'type' => 'page',
      'title' => $row->title,
      'status' => $row->status,
      'created' => $row->created,
      'changed' => $row->changed,
      'body' => [
        'summary' => $row->description,
        'value' => '',
      ],
      'field_representative_image' => $row->field_image_fid,
    ]);

    // Set the path alias if there is one.
    if ($row->alias) {
      $node_imported->path = '/' . $row->alias;
      $node_imported->path->pathauto = \Drupal\pathauto\PathautoState::SKIP;
    }

    // Create a section paragraph.
    $section = $paragraph_storage->create([
      'type' => 'section',
    ]);

    // Parse the `text_paragraph_values`.
    $text_paragraphs = explode('----------------------', $row->text_paragraph_values);

    foreach ($text_paragraphs as $text_content) {
      $text_component = $paragraph_storage->create([
        'type' => 'rich_text',
        'field_body' => [
          'value' => trim($text_content),
          'format' => 'basic_formatting',
        ],
      ]);
      $text_component->save();
      $section->field_components->appendItem($text_component);
    }

    $section->save();
    $node_imported->field_sections->appendItem($section);

    // Save the node.
    if ($node_imported->save()) {
      // Add a mapping to track that this node was imported.
      $node_mapping = $import_mapped_object_storage->create([
        'sourceid' => $row->nid,
        'destid' => $node_imported->id(),
        'type' => 'node',
      ]);
      $node_mapping->save();

      // Add the new node to the section collection.
      $collection_item = $entity_type_manager->getStorage('collection_item')->create([
        'collection' => $content_section->id(),
        'type' => 'default',
        'item' => $node_imported,
        'canonical' => 1,
      ]);

      $collection_item->save();

      // Add the new node to the Content Section menu.
      // Check if this collection has a section menu.
      foreach ($content_section->findItems('menu') as $collection_item_menu) {
        if ($collection_item_menu->getAttribute('section_collection_id') !== FALSE) {
          $section_menu = $collection_item_menu->item->entity;
          // Create the menu link. The parent value will be updated later.
          $menu_link_content = $entity_type_manager->getStorage('menu_link_content')->create([
            'title' => $row->menu_link_title,
            'menu_name' => $section_menu->id(),
            'link' => ['uri' => 'entity:node/' . $node_imported->id()],
            'expanded' => TRUE,
            'langcode' => 'en',
            'enabled' => !$row->menu_link_hidden,
            'weight' => $row->menu_link_weight,
          ]);
          $menu_link_content->save();

          // Add a mapping to track that this menu link was imported.
          $menu_link_mapping = $import_mapped_object_storage->create([
            'sourceid' => $row->menu_link_id,
            'destid' => $menu_link_content->id(),
            'type' => 'menu_link_content',
          ]);
          $menu_link_mapping->save();
        }
      }
    }
  }

  /**
   * Finish batch.
   *
   * This function is a static function to avoid serializing the ConfigSync
   * object unnecessarily.
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
      $messenger->addMessage(t('Section content import complete. Please verify that imported data is correct.'));

      if (!empty($results['errors'])) {
        $logger = \Drupal::logger('ilr');
        foreach ($results['errors'] as $error) {
          $messenger->addError($error);
          $logger->error($error);
        }
        $messenger->addWarning(t('The content section was imported with errors.'));
      }
    }
    else {
      // An error occurred.
      // $operations contains the operations that remained unprocessed.
      $error_operation = reset($operations);
      $message = t('An error occurred while processing %error_operation with arguments: @arguments', ['%error_operation' => $error_operation[0], '@arguments' => print_r($error_operation[1], TRUE)]);
      $messenger->addError($message);
    }
  }

}
