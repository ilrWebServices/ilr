<?php

namespace Drupal\ilr_migrate\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Determines a Collection based on a list of legacy terms.
 *
 * The expected input value should be a comma separated list of terms, such as
 * those included when setting the `node_terms` configuration on the
 * `ilr_d7_node` source plugin.
 *
 * Example:
 *
 * @code
 * process:
 *   type:
 *     plugin: term_collection
 * @endcode
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 *
 * @MigrateProcessPlugin(
 *   id = "term_collection"
 * )
 */
class TermCollection extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $tags = explode(',', $value);

    if ($this->in_array_any(['Scheinman Institute', 'Scheinman', 'NYC. Scheinman Institute'], $tags)) {
      // Scheinman blog.
      return 14;
    }

    if ($this->in_array_any(['ics'], $tags)) {
      // Institute for Compensation Studies subsite blog.
      return 24;
    }

    if ($this->in_array_any(['Buffalo Co-Lab News', 'High Road News', 'democracy buff', 'visiting scholar buffalo', 'buff econ geo'], $tags)) {
      // Buffalo Co-Lab subsite blog.
      return 35;
    }

    if ($this->in_array_any(['ldi'], $tags)) {
      // Labor Dynamics Institute subsite blog.
      return 36;
    }

    if ($this->in_array_any(['worker institute', 'workerinstitute', 'The Worker Institute', 'worker top'], $tags)) {
      // Worker Institute Blog.
      return 10;
    }

    if ($this->in_array_any(['mobilizing against inequality'], $tags)) {
      // Mobilizing Against Inequality
      return 37;
    }

    // News blog.
    return 26;
  }

  /**
   * Checks if any values exist in an array.
   *
   * @param array $needles
   *   The searched values.
   *
   * @param array $haystack
   *   The array to search.
   *
   * @return bool
   *   Returns true if any needles are found in the array, false otherwise.
   */
  protected function in_array_any($needles, $haystack) {
    return !empty(array_intersect($needles, $haystack));
  }

}
