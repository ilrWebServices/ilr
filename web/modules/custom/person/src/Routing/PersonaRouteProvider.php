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
    $route->setOption('parameters', [
      'person' => [
        'type' => 'entity:person',
      ],
    ]);
    return $route;
  }

  /**
   * {@inheritdoc}
   */
  protected function getAddFormRoute(EntityTypeInterface $entity_type) {
    $route = parent::getAddFormRoute($entity_type);
    $route->setDefault('_title_callback', PersonaController::class . '::addBundleTitle');
    $route->setOption('parameters', [
      'person' => [
        'type' => 'entity:person',
      ],
      'persona_type' => [
        'type' => 'entity:persona_type',
        'with_config_overrides' => TRUE
      ],
    ]);
    return $route;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditFormRoute(EntityTypeInterface $entity_type) {
    $route = parent::getEditFormRoute($entity_type);
    $route->setDefault('_title_callback', PersonaController::class . '::editTitle');
    $route->setOption('parameters', [
      'person' => [
        'type' => 'entity:person',
      ],
      'persona' => [
        'type' => 'entity:persona',
      ],
    ]);
    return $route;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeleteFormRoute(EntityTypeInterface $entity_type) {
    $route = parent::getDeleteFormRoute($entity_type);
    $route->setOption('parameters', [
      'person' => [
        'type' => 'entity:person',
      ],
      'persona' => [
        'type' => 'entity:persona',
      ],
    ]);

    return $route;
  }

  /**
   * {@inheritDoc}
   */
  protected function getCollectionRoute(EntityTypeInterface $entity_type) {
    $route = parent::getCollectionRoute($entity_type);
    $route->setDefault('_title_callback', PersonaController::class . '::collectionTitle');
    $route->setOption('parameters', [
      'person' => [
        'type' => 'entity:person',
      ],
    ]);
    $route->setOption('_admin_route', TRUE);

    return $route;
  }
}
