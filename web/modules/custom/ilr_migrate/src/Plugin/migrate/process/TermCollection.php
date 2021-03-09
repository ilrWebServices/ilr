<?php

namespace Drupal\ilr_migrate\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Determines a Collection based on finding a term in a list of terms.
 *
 * The expected input value should be a comma separated list of terms, such as
 * those included when setting the `node_terms` configuration on the
 * `ilr_d7_node` source plugin.
 *
 * Available configuration keys:
 * - map: An array (of 1 dimension) that defines the mapping between
 *   source values and destination values.
 * - default_value: The fallback value if there is no match in the map.
 *
 * Example:
 *
 * @code
 * process:
 *   type:
 *     plugin: term_collection
 *     map:
 *       term_name: collection_id
 *     default_value: 26
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

    foreach ($tags as $tag) {
      if (array_key_exists($tag, $this->configuration['map'])) {
        return $this->configuration['map'][$tag];
      }
    }

    return $this->configuration['default_value'];
  }

}
