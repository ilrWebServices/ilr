<?php

namespace Drupal\person\Routing;

use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\person\Controller\PersonaController;

/**
 * Provides routes for the persona entity.
 */
class PersonaRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  protected function getAddPageRoute(EntityTypeInterface $entity_type) {
    $route = parent::getAddPageRoute($entity_type);
    $route->setDefault('_controller', PersonaController::class . '::addPage');
    return $route;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCanonicalRoute(EntityTypeInterface $entity_type) {
    $route = parent::getCanonicalRoute($entity_type);
    $route->setDefault('_title_callback', PersonaController::class . '::title');
    return $route;
  }

  /**
   * {@inheritdoc}
   */
  protected function getAddFormRoute(EntityTypeInterface $entity_type) {
    $route = parent::getAddFormRoute($entity_type);
    $route->setDefault('_title_callback', PersonaController::class . '::addBundleTitle');
    return $route;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditFormRoute(EntityTypeInterface $entity_type) {
    $route = parent::getEditFormRoute($entity_type);
    $route->setDefault('_title_callback', PersonaController::class . '::editTitle');
    return $route;
  }

}
