<?php

namespace Drupal\ilr_migrate\Plugin\migrate\process;

use Drupal\migrate\Plugin\migrate\process\Explode;
// use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\Component\Utility\NestedArray;
// use Drupal\migrate\Plugin\migrate\process\StaticMap;

/**
 * Splits a delimited source string into an array, with optional cleanup.
 *
 * @see Drupal\migrate\Plugin\migrate\process\Explode
 *
 * Available configuration keys:
 * - map: An array (of 1 dimension) that defines the mapping between
 *   source values and destination values.
 * - remove: (optional) When this boolean is set to TRUE, items not in the map
 *   will be removed.
 *
 * Example:
 *
 * @code
 * process:
 *   type:
 *     plugin: explode_map
 *     map:
 *       from: to
 *       this: that
 *     remove: true
 * @endcode
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 *
 * @MigrateProcessPlugin(
 *   id = "explode_map"
 * )
 */
class ExplodeMap extends Explode {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $values = parent::transform($value, $migrate_executable, $row, $destination_property);
    $values = str_replace(array_keys($this->configuration['map']), array_values($this->configuration['map']), $values);

    if ($this->configuration['remove']) {
      return array_intersect(array_values($values), array_values($this->configuration['map']));
    }

    return $values;
  }

}
