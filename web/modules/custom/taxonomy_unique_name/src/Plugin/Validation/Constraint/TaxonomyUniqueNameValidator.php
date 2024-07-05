<?php

namespace Drupal\taxonomy_unique_name\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the TaxonomyUniqueName constraint.
 */
class TaxonomyUniqueNameValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   *
   * @var mixed $field
   */
  public function validate(mixed $value, Constraint $constraint) {
    if ($value->isEmpty()) {
      return;
    }

    /** @var \Drupal\taxonomy\TermInterface $term */
    $term = $value->getEntity();
    $vocab = $term->vid->entity;

    if (!$vocab->getThirdPartySetting('taxonomy_unique_name', 'unique')) {
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
    $query->accessCheck(FALSE);
    $query->condition('name', $term->label());
    $query->condition('vid', $vocab->id());

    // Don't include self when updating a term.
    if ($tid = $term->id()) {
      $query->condition('tid', $tid, '!=');
    }

    if ($result = $query->execute()) {
      $message = $vocab->getThirdPartySetting('taxonomy_unique_name', 'unique_message') ?? $constraint->duplicate;

      $this->context->addViolation($message, [
        '%term' => $term->label(),
        '%vocabulary'=> $vocab->label(),
      ]);
    }
  }

}
