<?php

namespace Drupal\collection;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\collection\Entity\CollectionTypeInterface;

/**
 * Provides dynamic permissions for each collection type.
 */
class CollectionPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * CollectionPermissions constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * Returns an array of collection type permissions.
   *
   * @return array
   *   The collection type permissions.
   *
   * @see \Drupal\user\PermissionHandlerInterface::getPermissions()
   */
  public function collectionTypePermissions() {
    $perms = [];
    $collection_types = $this->entityTypeManager->getStorage('collection_type')->loadMultiple();

    foreach ($collection_types as $type) {
      $perms += $this->buildPermissions($type);
    }

    return $perms;
  }

  /**
   * Returns a list of collection permissions for a given collection type.
   *
   * @param \Drupal\collection\Entity\CollectionTypeInterface $type
   *   The collection type.
   *
   * @return array
   *   An associative array of permission names and descriptions.
   */
  protected function buildPermissions(CollectionTypeInterface $type) {
    $type_id = $type->id();
    $type_params = ['%type_name' => $type->label()];

    return [
      "create $type_id collection" => [
        'title' => $this->t('%type_name: Create new collection', $type_params),
      ],
      "view $type_id collection" => [
        'title' => $this->t('%type_name: View collection', $type_params),
      ],
    ];
  }

}
