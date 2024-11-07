<?php

namespace Drupal\person;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines an access control handler for persona entities.
 */
class PersonaAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * Constructs a PersonaAccessControlHandler object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    protected EntityTypeManagerInterface $entityTypeManager
  ) {
    parent::__construct($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\person\PersonaInterface $entity */
    // Allow admin permission to override all operations.
    if ($account->hasPermission($this->entityType->getAdminPermission())) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    $type = $entity->bundle();

    switch ($operation) {
      case 'view':
        if ($entity->isPublished()) {
          $access_result = AccessResult::allowed()
            ->cachePerPermissions()
            ->addCacheableDependency($entity);
        }
        else {
          $access_result = AccessResult::neutral()
            ->cachePerPermissions()
            ->addCacheableDependency($entity);
        }
        return $access_result;

      case 'update':
        if ($account->hasPermission('edit any ' . $type . ' persona')) {
          return AccessResult::allowed()->cachePerPermissions();
        }
        return AccessResult::neutral("The following permission is required: 'edit any $type persona'.")->cachePerPermissions();

      case 'delete':
        if ($account->hasPermission('delete any ' . $type . ' persona')) {
          return AccessResult::allowed()->cachePerPermissions();
        }
        return AccessResult::neutral("The following permission is required: 'delete any $type persona'.")->cachePerPermissions();

      default:
        return AccessResult::neutral()->cachePerPermissions();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    $permissions = [
      'administer persons',
      'create ' . $entity_bundle . ' persona',
    ];
    return AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');
  }

}
