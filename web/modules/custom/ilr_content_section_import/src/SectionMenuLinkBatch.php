<?php

namespace Drupal\ilr_content_section_import;

/**
 * Methods for running the section menu link importer in a batch.
 *
 * @see \Drupal\ilr_content_section_import\Form\SectionImportBatch::process()
 */
class SectionMenuLinkBatch {

  /**
   * Processes the section import batch and persists the importer.
   *
   * @param array $row
   *   The a row of data to import as a node.
   * @param array $context
   *   The batch context.
   */
  public static function process(array $row, array &$context) {
    $entity_type_manager = \Drupal::entityTypeManager();
    $menu_link_storage = $entity_type_manager->getStorage('menu_link_content');
    $import_mapped_object_storage = $entity_type_manager->getStorage('section_import_mapped_object');

    // Load the mapping for this menu link source.
    $menu_link_mapping = $import_mapped_object_storage->loadByProperties([
      'type' => 'menu_link_content',
      'sourceid' => $row->menu_link_id,
    ]);

    if (empty($menu_link_mapping)) {
      return;
    }

    $menu_link_mapping = reset($menu_link_mapping);

    // Load the mapping for the menu link source parent.
    $parent_link_mapping = $import_mapped_object_storage->loadByProperties([
      'type' => 'menu_link_content',
      'sourceid' => $row->menu_link_parent_id,
    ]);

    $plid = 0;

    if (!empty($parent_link_mapping)) {
      $parent_link_mapping = reset($parent_link_mapping);
      // Load the menu_link_content by destid so we can get the uuid.
      $parent_menu_link = $menu_link_storage->loadByProperties([
        'id' => $parent_link_mapping->destid->value,
      ]);

      $parent_menu_link = reset($parent_menu_link);
      $plid = 'menu_link_content:' . $parent_menu_link->uuid();
    }

    // Load the imported menu link and update the parent.
    $imported_menu_link = $menu_link_storage->loadByProperties([
      'id' => $menu_link_mapping->destid->value,
    ]);
    $imported_menu_link = reset($imported_menu_link);
    $imported_menu_link->parent = $plid;
    $imported_menu_link->save();
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
  public static function finish($success, array $results, array $operations) {
    $messenger = \Drupal::messenger();

    if ($success) {
      $messenger->addMessage(t('Menu link update complete.'));

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
      $message = t('An error occurred while processing %error_operation with arguments: @arguments', [
        '%error_operation' => $error_operation[0],
        '@arguments' => print_r($error_operation[1], TRUE),
      ]);
      $messenger->addError($message);
    }
  }

}
