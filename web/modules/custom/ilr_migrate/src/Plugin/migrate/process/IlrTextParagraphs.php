<?php

namespace Drupal\ilr_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Transform delimited D7 text paragraphs into D8 text paragraphs.
 *
 * @MigrateProcessPlugin(
 *   id = "ilr_text_paragraphs"
 * )
 *
 * Example:
 *
 * @code
 * _text_paragraphs:
 *   plugin: ilr_text_paragraphs
 *   source: text_paragraph_values
 * @endcode
 *
 */
class IlrTextParagraphs extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $text_paragraph_ids = [];

    // Split the row value into an array on the `----------------------` delimiter.
    $source_text_values = explode('----------------------', $value);

    // Create a rich text paragraph for each new paragraph.
    foreach ($source_text_values as $source_text_value) {

    }

    // Save the new text paragraph.

    // Return an array of text paragraph target_ids (and revision ids?) for later processing.
    return $text_paragraph_ids;
  }

}
