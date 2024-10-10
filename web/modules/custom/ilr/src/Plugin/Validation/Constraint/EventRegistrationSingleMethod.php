<?php

namespace Drupal\ilr\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that field_external.
 *
 * @Constraint(
 *   id = "EventRegistrationSingleMethod",
 *   label = @Translation("Ensure a single event registration method, e.g. form or URL", context = "Validation")
 * )
 */
class EventRegistrationSingleMethod extends Constraint {

  public $message = 'Please choose either a registration form or registration URL, but not both.';

}
