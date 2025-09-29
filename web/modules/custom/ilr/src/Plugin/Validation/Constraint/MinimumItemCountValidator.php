<?php

namespace Drupal\ilr\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the MinimumItemCount constraint.
 */
class MinimumItemCountValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   *
   * @var Drupal\paragraphs\ParagraphInterface $paragraph
   */
  public function validate(mixed $paragraph, Constraint $constraint) {
    if (!$paragraph->hasField('field_carousel_items') || $paragraph->field_carousel_items->isEmpty()) {
      return;
    }

    if ($paragraph->field_carousel_items->count() < $constraint->minItems) {
      $this->context->addViolation($constraint->messageBelowMinimum, [
        '@item_kind' => $paragraph->type->entity->label(),
        '@number' => $constraint->minItems,
      ]);
    }
  }

}
