<?php

namespace Drupal\collection_subsites;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\collection\CollectionContentManager;
use Drupal\Core\Entity\EntityInterface;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * The `collection_subsites.resolver` service.
 */
class CollectionSubsitesResolver {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The collection content manager.
   *
   * @var \Drupal\collection\CollectionContentManager
   */
  protected $collectionContentManager;

  /**
   * Static cache of subsite entities, keyed by item entity ID.
   *
   * @var array
   */
  protected $subsiteCollectionEntities = [];

  /**
   * Construct a new `collection_subsites.resolver` service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\collection\CollectionContentManager $collection_content_manager
   *   The collection content manager service.
   */
  public function __construct(EntityTypeManager $entity_type_manager, CollectionContentManager $collection_content_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->collectionContentManager = $collection_content_manager;
  }

  /**
   * Get the subsite for a given entity.
   *
   * @param Drupal\Core\Entity\EntityInterface $entity
   *   A content or configuration entity.
   * @param string $path
   *   An existing path to check. Used for recursion.
   *
   * @return Drupal\collection\Entity\CollectionInterface||false
   *   A collection entity or FALSE if the entity is not in a subsite.
   */
  public function getSubsite(EntityInterface $entity, $path = NULL) {
    $entity_key = $entity->uuid();

    if (isset($this->subsiteCollectionEntities[$entity_key])) {
      return $this->subsiteCollectionEntities[$entity_key];
    }

    $subsite_collection = FALSE;

    if ($entity instanceof CollectionInterface && $this->isSubsite($entity)) {
      if (!$path || strpos($path, $entity->toUrl()->toString()) === 0) {
        $subsite_collection = $entity;
      }
    }
    elseif ($entity instanceof ContentEntityInterface) {
      $entity_path = $path ?? $entity->toUrl()->toString();
      $collection_items = $this->collectionContentManager->getCollectionItemsForEntity($entity);

      foreach ($collection_items as $item) {
        $collection = $item->collection->entity;
        $collection_path = $collection->toUrl()->toString();

        if ($this->isSubsite($collection) && strpos($entity_path, $collection_path) === 0) {
          $subsite_collection = $collection;
        }
        else {
          $subsite_collection = $this->getSubsite($collection, $entity_path);
        }
      }
    }

    // Add this entity to the static cache.
    $this->subsiteCollectionEntities[$entity_key] = $subsite_collection;

    return $subsite_collection;
  }

  /**
   * Check whether a collection entity is a subsite.
   *
   * @return bool
   *   True if collection is a subsite.
   */
  protected function isSubsite(CollectionInterface $collection) {
    $collection_type = $this->entityTypeManager->getStorage('collection_type')->load($collection->bundle());
    return (bool) $collection_type->getThirdPartySetting('collection_subsites', 'contains_subsites');
  }

}
