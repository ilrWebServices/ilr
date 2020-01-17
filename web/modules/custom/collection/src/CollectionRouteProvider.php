<?php

namespace Drupal\collection;

use Drupal\Core\Entity\Controller\EntityController;
use Drupal\collection\Controller\CollectionItemController;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for the collection entity.
 */
class CollectionRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  protected function getCollectionRoute(EntityTypeInterface $entity_type) {
    $route = parent::getCollectionRoute($entity_type);
    // Change the Collection listing page permission from the value defined in
    // the `admin_permission` annotation.
    $route->setRequirement('_permission', 'access collection overview');
    return $route;
  }

}
