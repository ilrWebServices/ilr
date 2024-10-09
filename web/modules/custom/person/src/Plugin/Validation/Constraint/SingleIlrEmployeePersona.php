<?php

namespace Drupal\person\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that an entity is not canonical in more than one collection.
 *
 * @Constraint(
 *   id = "SingleIlrEmployeePersona",
 *   label = @Translation("Single ILR Employee Persona", context = "Validation"),
 *   type = "string"
 * )
 */
class SingleIlrEmployeePersona extends Constraint {

  public $existing = 'There is already an ILR Employee persona for %person.';

}
