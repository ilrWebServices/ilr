<?php

namespace Drupal\collection\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Validates the SingleCanonicalItem constraint.
 */
class SingleCanonicalItemValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * @var Drupal\Core\Entity\Sql\SqlContentEntityStorage
   */
  protected $collectionItemStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->collectionItemStorage = $container->get('entity_type.manager')->getStorage('collection_item');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function validate($collection_item, Constraint $constraint) {
    // Ensure that we only validate collection items that are set to canonical.
    if (!$collection_item->canonical->value) {
      return;
    }

    /** @var Drupal\Core\Entity\EntityInterface $item_entity */
    $item_entity = $collection_item->item->entity;

    $canonical_collection_item_query = $this->collectionItemStorage->getQuery()
      ->condition('item__target_type', $item_entity->getEntityTypeId())
      ->condition('item__target_id', $item_entity->id())
      ->condition('canonical', 1);

    if ($collection_item->isNew() === FALSE) {
      $canonical_collection_item_query->condition('id', $collection_item->id(), '!=');
    }

    $canonical_collection_item_ids = $canonical_collection_item_query->execute();

    if (!empty($canonical_collection_item_ids)) {
      $canonical_collection_item = $this->collectionItemStorage->load(reset($canonical_collection_item_ids));

      $this->context->addViolation($constraint->existing_canonical, [
        '%entity' => $item_entity->label(),
        '%collection' => $canonical_collection_item->collection->entity->label(),
      ]);
    }
  }

}
