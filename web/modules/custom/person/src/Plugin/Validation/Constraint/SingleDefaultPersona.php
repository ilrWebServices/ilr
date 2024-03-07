<?php

namespace Drupal\person\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that an entity is not canonical in more than one collection.
 *
 * @Constraint(
 *   id = "SingleDefaultPersona",
 *   label = @Translation("Single Default Persona", context = "Validation"),
 *   type = "string"
 * )
 */
class SingleDefaultPersona extends Constraint {

  public $existing_default = 'There is already a default persona for %person.';

}
