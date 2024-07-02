<?php

namespace Drupal\ilr\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that there is not an existing term with the same name.
 *
 * @Constraint(
 *   id = "UniqueTermName",
 *   label = @Translation("Unique term name", context = "Validation")
 * )
 */
class UniqueTermName extends Constraint {

  // The message that will be shown if there is an existing term with the same name.
  public $duplicate = '%name already exists in %vocab. You may wish to use the existing term rather than creating a new one.';

}
