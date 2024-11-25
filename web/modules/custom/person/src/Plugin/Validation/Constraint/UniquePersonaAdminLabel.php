<?php

namespace Drupal\person\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Prevents duplicate persona admin_labels.
 *
 * @Constraint(
 *   id = "UniquePersonaAdminLabel",
 *   label = @Translation("Unique Persona Admin Label", context = "Validation")
 * )
 */
class UniquePersonaAdminLabel extends Constraint {

  // The message that will be shown if the admin label is used in another persona.
  public $duplicate = 'There is an existing persona with the admin label %label.';

}
