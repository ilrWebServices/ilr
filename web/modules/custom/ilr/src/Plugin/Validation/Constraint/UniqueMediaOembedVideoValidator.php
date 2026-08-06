<?php

namespace Drupal\ilr\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\media\MediaInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the UniqueMediaOembedVideoValidator constraint.
 */
class UniqueMediaOembedVideoValidator extends ConstraintValidator {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $entity, Constraint $constraint): void {
    if (!$entity instanceof MediaInterface) {
      throw new \InvalidArgumentException(
        sprintf('The validated value must be instance of \Drupal\media\MediaInterface, %s was given.', get_debug_type($entity))
      );
    }

    if (!$entity->hasField('field_media_oembed_video')) {
      return;
    }

    if ($entity->field_media_oembed_video->isEmpty()) {
      return;
    }

    // If editing existing entity and there's no change to the field, skip
    // validation. This allows for saving of existing dupes.
    if (!$entity->isNew()) {
      $original_media_entity = \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId())->loadUnchanged($entity->id());

      if ($entity->field_media_oembed_video->value === $original_media_entity->field_media_oembed_video->value) {
        return;
      }
    }

    $query = \Drupal::entityTypeManager()->getStorage('media')->getQuery();
    $query->accessCheck(TRUE);
    $query->condition('field_media_oembed_video', $entity->field_media_oembed_video->value);

    // Don't include self when checking.
    if ($mid = $entity->id()) {
      $query->condition('mid', $mid, '!=');
    }

    if ($result = $query->execute()) {
      $this->context->addViolation($constraint->duplicate, [
        '%url' => $entity->field_media_oembed_video->value,
      ]);
    }
  }

}
