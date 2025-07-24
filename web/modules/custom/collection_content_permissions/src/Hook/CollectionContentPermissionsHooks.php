<?php

namespace Drupal\collection_content_permissions\Hook;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\collection\Entity\CollectionItemInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;

class CollectionContentPermissionsHooks {

  #[Hook('entity_field_access_alter')]
  public function canonicalStatusFieldAccess(array &$grants, array $context): void {
    if ($context['field_definition']->getTargetEntityTypeId() === 'node' && $context['field_definition']->getName() === 'status' && $context['operation'] === 'edit') {
      if ($context['account']->hasPermission('administer nodes')) {
        return;
      }

      $collection_items = \Drupal::service('collection.content_manager')->getCollectionItemsForEntity($context['items']->getEntity(), FALSE);

      foreach ($collection_items as $collection_item) {
        if ($collection_item->isCanonical() && $collection_item->collection->entity->access('update', $context['account'])) {
          $grants[':default'] = AccessResult::allowedIfHasPermission($context['account'], 'override status for canonical content in editable collections');
          return;
        }
      }
    }
  }

  #[Hook('entity_access')]
  public function canonicalUnpublishedViewAccess(EntityInterface $entity, $op, AccountInterface $account): AccessResultInterface {
    if ($op !== 'view'
      || $entity instanceof ConfigEntityInterface
      || $entity instanceof CollectionItemInterface
      || $entity instanceof CollectionInterface
      || ($entity instanceof EntityPublishedInterface && $entity->isPublished())
    ) {
      return AccessResult::neutral();
    }

    $collection_items = \Drupal::service('collection.content_manager')->getCollectionItemsForEntity($entity, FALSE);

    foreach ($collection_items as $collection_item) {
      if ($collection_item->isCanonical() && $collection_item->collection->entity->access('update', $account)) {
        return AccessResult::allowedIfHasPermission($account, 'view any unpublished canonical content in editable collections');
      }
    }

    return AccessResult::neutral();
  }

  #[Hook('entity_access')]
  public function canonicalEditAccess(EntityInterface $entity, $op, AccountInterface $account): AccessResultInterface {
    if ($op === 'update') {
      $collection_items = \Drupal::service('collection.content_manager')->getCollectionItemsForEntity($entity, FALSE);

      foreach ($collection_items as $collection_item) {
        if ($collection_item->isCanonical() && $collection_item->collection->entity->access('update', $account)) {
          return AccessResult::allowedIfHasPermission($account, 'edit any canonical content in editable collections');
        }
      }
    }

    return AccessResult::neutral();
  }

}
