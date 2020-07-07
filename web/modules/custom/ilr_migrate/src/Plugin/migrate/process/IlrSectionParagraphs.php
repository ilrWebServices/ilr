<?php

namespace Drupal\ilr_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Add some existing paragraphs into a section paragraph.
 *
 * @MigrateProcessPlugin(
 *   id = "ilr_section_paragraphs"
 * )
 *
 * Example:
 *
 * @code
 * field_sections:
 *   plugin: ilr_section_paragraphs
 *   source: text_paragraph_values
 * @endcode
 *
 */
class IlrSectionParagraphs extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // Create a new section paragraph.

    // Add the incoming paragraphs to the new section paragraph.

    // Save the section paragraph.

    // Return the section paragraph target_id (and revision id?).
    return strrev($value);
  }

}
