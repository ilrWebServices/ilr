<?php

namespace Drupal\ilr_migrate\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Converts a string of multiple links with two delimiters into an array of arrays.
 *
 * @see https://drupal.stackexchange.com/a/314909
 *
 * @MigrateProcessPlugin(
 *   id = "double_delim_links"
 * )
 */
class DoubleDelimLinks extends ProcessPluginBase {

  /**
  * {@inheritdoc}
  */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $value_array = [];

    foreach (explode("\n", $value) as $item) {
      $parts = explode("\t", $item);
      $value_array[] = [
        'uri' => $parts[0],
        'title' => $parts[1] ?? $parts[0],
      ];
    }

    return $value_array;
  }

}
