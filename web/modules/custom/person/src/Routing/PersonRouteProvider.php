<?php

namespace Drupal\person\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides HTML routes for person entities.
 */
class PersonRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = parent::getRoutes($entity_type);
    $entity_type_id = $entity_type->id();

    if ($personas_page_route = $this->getPersonasPageRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.personas", $personas_page_route);
    }

    return $collection;
  }

  /**
   * Gets the persona route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getPersonasPageRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('personas') && $entity_type->hasViewBuilderClass()) {
      $entity_type_id = $entity_type->id();
      $route = new Route($entity_type->getLinkTemplate('personas'));
      $route
        ->addDefaults([
          '_controller' => "\Drupal\person\Controller\PersonPersonasController::content",
          '_title_callback' => '\Drupal\person\Controller\PersonPersonasController::title',
        ])
        ->setRequirement('_permission', 'administer persons')
        ->setOption('_admin_route', 'TRUE')
        ->setOption('parameters', [
          $entity_type_id => ['type' => 'entity:' . $entity_type_id],
        ]);

      return $route;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getCanonicalRoute(EntityTypeInterface $entity_type) {
    return parent::getEditFormRoute($entity_type);
  }

}
