<?php

namespace Drupal\collection\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that an entity is not canonical in more than one collection.
 *
 * @Constraint(
 *   id = "SingleCanonicalItem",
 *   label = @Translation("Single Canonical Item", context = "Validation"),
 *   type = "string"
 * )
 */
class SingleCanonicalItem extends Constraint {

  public $existing_canonical = '%entity is already canonical (e.g. primary) in %collection.';

}
