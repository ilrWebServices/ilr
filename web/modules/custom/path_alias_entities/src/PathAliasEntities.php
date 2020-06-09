<?php

namespace Drupal\path_alias_entities;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\Core\Url;

/**
 * Class definition for PathAliasEntities service.
 */
class PathAliasEntities {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Entity\EntityTypeBundleInfoInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * Symfony\Component\HttpFoundation\RequestStack definition.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Drupal\path_alias\AliasManagerInterface definition.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $pathAliasManager;

  /**
   * Constructs a new PathAliasEntities service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, RequestStack $request_stack, AliasManagerInterface $path_alias_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->requestStack = $request_stack;
    $this->pathAliasManager = $path_alias_manager;
  }

  /**
   * Load all the entities on the path.
   *
   * @return array
   *   Entities that are represented by the pieces of the path.
   */
  public function getPathAliasEntities() {
    $entities = [];
    $alias = $this->requestStack->getCurrentRequest()->getPathInfo();

    // We start by checking if the current path is actually an alias.
    if ($alias === $this->pathAliasManager->getPathByAlias($alias)) {
      return $entities;
    }

    $partial_alias = '';
    $parts = explode('/', ltrim($alias, '/'));

    foreach ($parts as $part) {
      $entity = NULL;
      $partial_alias = $partial_alias . '/' . $part;

      // Check the partial alias to see if there is a path it represents.
      if ($partial_alias !== $this->pathAliasManager->getPathByAlias($partial_alias)) {
        // If a path is found, load its route parameters.
        if ($params = Url::fromUri("internal:" . $partial_alias)->getRouteParameters()) {
          $entity_type = key($params);
          $entity = $this->entityTypeManager->getStorage($entity_type)->load($params[$entity_type]);
        }
      }

      if ($entity) {
        $entities[$partial_alias] = $entity;
      }
    }

    return $entities;
  }

}
