<?php

namespace Drupal\ilr\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the EventRegistrationSingleMethod constraint.
 */
class EventRegistrationSingleMethodValidator extends ConstraintValidator {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   *
   * @var Drupal\Core\Field\FieldItemList $field_url
   */
  public function validate($field_url, Constraint $constraint) {
    if ($field_url->isEmpty()) {
      return;
    }

    if ($field_url->getEntity()->get('field_registration_form')->isEmpty() === FALSE) {
      $this->context->addViolation($constraint->message);
    }
  }

}
