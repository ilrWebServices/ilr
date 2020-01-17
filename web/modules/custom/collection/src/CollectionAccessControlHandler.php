<?php

namespace Drupal\collection;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\collection\Access\CollectionOwnerTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Collection entity.
 *
 * @see \Drupal\collection\Entity\Collection.
 */
class CollectionAccessControlHandler extends EntityAccessControlHandler {

  use CollectionOwnerTrait;

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($account->hasPermission('administer collections')) {
      return AccessResult::allowed();
    }

    if ($entity->getEntityTypeId() === 'collection') {
      $collection_entity = $entity;
    }
    elseif ($entity->getEntityTypeId() === 'collection_item') {
      $collection_entity = $entity->collection->entity;
    }

    $type = $collection_entity->bundle();
    $is_owner = $this->isOwner($collection_entity, $account);
    $is_published = $collection_entity->isPublished();

    switch ($operation) {
      case 'view':
        // Allow user if they own this collection and have the proper
        // permission. This includes unpublished collections.
        if ($account->hasPermission('view own collections') && $is_owner) {
          return AccessResult::allowed();
        }
        // Allow user if they have the 'view {collection_type} permission' and
        // the collection is published.
        elseif ($account->hasPermission('view ' . $type . ' collection') && $is_published) {
          return AccessResult::allowed();
        }
        return AccessResult::neutral("The user must be an owner and have the 'view own collections' permission, or the user must have the 'view $type collection' permission and the collection must be published.");

      case 'update':
        // Allow user if they own this collection and have the proper
        // permission. This includes unpublished collections.
        if ($account->hasPermission('edit own collections') && $is_owner) {
          return AccessResult::allowed();
        }
        return AccessResult::neutral("The user must be an owner and have the 'edit own collections' permission.");

      case 'delete':
        // Allow user if they own this collection and have the proper
        // permission. This includes unpublished collections.
        if ($account->hasPermission('delete own collections') && $is_owner) {
          return AccessResult::allowed();
        }
        return AccessResult::neutral("The user must be an owner and have the 'delete own collections' permission.");

      default:
        return AccessResult::neutral();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    $permissions = [
      'administer collections',
      'create ' . $entity_bundle . ' collection',
    ];
    return AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');
  }

}
