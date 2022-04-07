<?php

namespace Drupal\collection_revision_ui;

use Drupal\collection\Entity\CollectionTypeInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds revision permissions for Collections.
 */
class CollectionRevisionUiPermissions implements ContainerInjectionInterface {

  /**
   * Collection type storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $collectionTypeStorage;

  /**
   * CollectionRevisionUiPermissions constructor.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $collectionTypeStorage
   *   Collection type storage.
   */
  public function __construct(EntityStorageInterface $collectionTypeStorage) {
    $this->collectionTypeStorage = $collectionTypeStorage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('collection_type')
    );
  }

  /**
   * Generate dynamic permissions.
   */
  public function permissions() {
    $permissions = [];

    foreach ($this->collectionTypeStorage->loadMultiple() as $bundle => $collection_type) {
      assert($collection_type instanceof CollectionTypeInterface);
      $bundle_label = $collection_type->label();
      $permissions['view collection ' . $bundle . ' history'] = [
        'title' => 'View ' . $bundle_label . ' history pages',
      ];
      $permissions['view collection ' . $bundle . ' revisions'] = [
        'title' => 'View ' . $bundle_label . ' revisions pages',
      ];
      $permissions['revert collection ' . $bundle . ' revisions'] = [
        'title' => 'Revert ' . $bundle_label . ' revisions',
      ];
      $permissions['delete collection ' . $bundle . ' revisions'] = [
        'title' => 'Delete ' . $bundle_label . ' revisions',
      ];
    }

    return $permissions;
  }

}
