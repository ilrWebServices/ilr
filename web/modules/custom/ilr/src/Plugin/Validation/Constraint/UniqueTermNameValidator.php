<?php

namespace Drupal\ilr\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the UniqueTermName constraint.
 */
class UniqueTermNameValidator extends ConstraintValidator {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   *
   * @var Drupal\Core\Field\FieldItemList $name
   */
  public function validate($name, Constraint $constraint) {
    if ($name->isEmpty()) {
      return;
    }

    $term = $name->getEntity();
    $vocab = $term->vid->entity;

    if (!$vocab->getThirdPartySetting('ilr', 'force_unique')) {
      return;
    }

    // If editing existing entity, skip validation.
    if (!$term->isNew()) {
      $original_entity = \Drupal::entityTypeManager()->getStorage($term->getEntityTypeId())->loadUnchanged($term->id());

      if ($term->label() === $original_entity->label()) {
        return;
      }
    }

    $query = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->getQuery();
    $query->accessCheck(TRUE);
    $query->condition('name', $term->label());
    $query->condition('vid', $vocab->id());

    // Don't include self when updating a term.
    if ($tid = $term->id()) {
      $query->condition('tid', $tid, '!=');
    }

    if ($result = $query->execute()) {
      $this->context->addViolation($constraint->duplicate, [
        '%name' => $term->label(),
        '%vocab'=> $vocab->label(),
      ]);
    }
  }

}
