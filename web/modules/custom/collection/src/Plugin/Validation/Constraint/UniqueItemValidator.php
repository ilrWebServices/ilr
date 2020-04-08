<?php

namespace Drupal\collection\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the UniqueItem constraint.
 */
class UniqueItemValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($collection_item, Constraint $constraint) {
    /** @var Drupal\collection\Entity\CollectionItemInterface $collection_item */
    $collection = $collection_item->collection->entity;

    /** @var Drupal\Core\Entity\EntityInterface $item_entity */
    $item_entity = $collection_item->item->entity;

    if ($collection_item->isNew() && $collection->getItem($item_entity)) {
      $this->context->addViolation($constraint->duplicate, [
        '%entity' => $item_entity->label(),
        '%collection' => $collection->label(),
      ]);
    }
  }

}
