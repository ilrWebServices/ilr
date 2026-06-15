<?php

namespace Drupal\collection_subsites\Cache;

use Drupal\book\BookManagerInterface;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Drupal\path_alias_entities\PathAliasEntities;

/**
 * Defines the book navigation cache context service.
 *
 * Cache context ID: 'route.subsite_collection'.
 *
 * This allows for subsite_collection location-aware caching. It depends on:
 * - whether the current route is in a path that's part of a subsite collection
 */
class CollectionCacheContext implements CacheContextInterface {

  /**
   * Constructs a new BookNavigationCacheContext service.
   */
  public function __construct(
    protected RouteMatchInterface $routeMatch,
    protected PathAliasEntities $pathAliasEntities
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t("Subsite collection");
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    foreach (array_reverse($this->pathAliasEntities->getPathAliasEntities()) as $path_entity) {
      if ($path_entity instanceof CollectionInterface) {
        $collection_type = $path_entity->type->entity;

        if ((bool) $collection_type->getThirdPartySetting('collection_subsites', 'contains_subsites')) {
          $collection = $path_entity;
          return $collection->id();
        }
      }
    }

    return 'subsite_collection.none';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    return new CacheableMetadata();
  }

}
