<?php

declare(strict_types=1);

namespace Drupal\ilr\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the Unique media item per Video Post constraint.
 */
final class UniqueVideoPostMediaConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructs the object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $items, Constraint $constraint): void {
    if (!$items instanceof EntityReferenceFieldItemList) {
      throw new \InvalidArgumentException(
        sprintf('The validated value must be instance of \Drupal\Core\Field\EntityReferenceFieldItemList, %s was given.', get_debug_type($items))
      );
    }

    $entity = $items->getEntity();

    // If editing existing entity and there's no change to the field, skip
    // validation. This allows for saving of existing dupes.
    if (!$entity->isNew()) {
      $original_entity = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->loadUnchanged($entity->id());

      if ($entity->field_video->value === $original_entity->field_video->value) {
        return;
      }
    }

    $media_entity_ids = [];

    foreach ($items as $item) {
      $media_entity_ids[] = $item->target_id;
    }

    if (empty($media_entity_ids)) {
      return;
    }

    $node_storage = $this->entityTypeManager->getStorage('node');
    $query = $node_storage->getQuery();
    $query->accessCheck(TRUE);
    $query->condition('field_video', $media_entity_ids, 'IN');

    // Don't include self when checking.
    if ($nid = $entity->id()) {
      $query->condition('nid', $nid, '!=');
    }

    if ($result = $query->execute()) {
      $this->context->addViolation($constraint->message, [
        '@create_crosspost_url' => Url::fromRoute('collection.node.collections', [
          'node' => reset($result),
        ])->toString(),
      ]);
    }
  }

}
