<?php

namespace Drupal\collection\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Drupal\collection\Entity\Collection;
use Drupal\Core\Entity\EntityInterface;

/**
 * Validates the UniqueItem constraint.
 */
class UniqueItemValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    foreach ($items as $item) {
      $collection = \Drupal::routeMatch()->getParameter('collection');

      if ($this->inCollection($item->entity, $collection)) {
        $this->context->addViolation($constraint->duplicate, [
          '%entity' => $item->entity->label(),
          '%collection' => $collection->label(),
        ]);
      }
    }
  }

  /**
   * Check whether the item is already in the collection.
   *
   * @param Drupal\Core\Entity\EntityInterface $entity
   *   The entity attempting to be added.
   * @param Drupal\collection\Entity\Collection $collection
   *   The collection entity.
   */
  private function inCollection(EntityInterface $entity, Collection $collection) {
    if ($collection->getItem($entity)) {
      return TRUE;
    }

    return FALSE;
  }
}
