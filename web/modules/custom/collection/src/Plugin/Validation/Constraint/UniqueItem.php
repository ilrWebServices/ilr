<?php

namespace Drupal\collection\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the entity is not already in the collection.
 *
 * @Constraint(
 *   id = "UniqueItem",
 *   label = @Translation("Unique Item", context = "Validation"),
 *   type = "string"
 * )
 */
class UniqueItem extends Constraint {

  // The message that will be shown if the item is in the collection.
  public $duplicate = '%entity is already in the %collection collection.';
}
