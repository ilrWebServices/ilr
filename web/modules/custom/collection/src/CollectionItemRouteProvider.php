<?php

namespace Drupal\collection;

use Drupal\Core\Entity\Controller\EntityController;
use Drupal\collection\Controller\CollectionItemController;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for the collection item entity.
 */
class CollectionItemRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  protected function getAddPageRoute(EntityTypeInterface $entity_type) {
    $route = parent::getAddPageRoute($entity_type);
    $route->setDefault('_controller', CollectionItemController::class . '::addPage');
    $route->setOption('parameters', [
      'collection' => [
        'type' => 'entity:collection',
      ],
    ]);
    return $route;
  }

  /**
   * {@inheritdoc}
   */
  protected function getAddFormRoute(EntityTypeInterface $entity_type) {
    $route = parent::getAddFormRoute($entity_type);
    $route->setDefault('_title_callback', CollectionItemController::class . '::addBundleTitle');
    $route->setOption('parameters', [
      'collection' => [
        'type' => 'entity:collection',
      ],
      'collection_item_type' => [
        'type' => 'entity:collection_item_type',
        'with_config_overrides' => true
      ],
    ]);
    return $route;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditFormRoute(EntityTypeInterface $entity_type) {
    $route = parent::getEditFormRoute($entity_type);
    $route->setOption('parameters', [
      'collection' => [
        'type' => 'entity:collection',
      ],
      'collection_item' => [
        'type' => 'entity:collection_item',
      ],
    ]);
    return $route;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeleteFormRoute(EntityTypeInterface $entity_type) {
    $route = parent::getDeleteFormRoute($entity_type);
    // $route->setDefault('_title_callback', EntityController::class . '::deleteTitle');
    $route->setOption('parameters', [
      'collection' => [
        'type' => 'entity:collection',
      ],
      'collection_item' => [
        'type' => 'entity:collection_item',
      ],
    ]);

    return $route;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeleteMultipleFormRoute(EntityTypeInterface $entity_type) {
    $route = parent::getDeleteMultipleFormRoute($entity_type);
    $route->setOption('parameters', [
      'collection' => [
        'type' => 'entity:collection',
      ],
    ]);

    return $route;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCollectionRoute(EntityTypeInterface $entity_type) {
    $route = new Route($entity_type->getLinkTemplate('collection'));
    $route
      ->addDefaults([
        '_entity_list' => 'collection_item',
        '_title_callback' => CollectionItemController::class . '::title',
      ])
      ->setRequirement('_collection_items_access', 'TRUE')
      ->setOption('parameters', [
        'collection' => [
          'type' => 'entity:collection',
        ],
      ])
      ->setOption('_admin_route', TRUE);

    return $route;
  }

}
