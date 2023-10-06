<?php

namespace Drupal\collection_resource_library;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\collection_tools\CollectionVocabularyTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class CollectionResourceLibraryManager {

  use CollectionVocabularyTrait;

  // @todo Refactor to load and analyze all collection_item types to determine if they can contain resource items and cache here.
  public function __construct(
    public EntityTypeManagerInterface $entityTypeManager
  ) {}

  /**
   * Determine if a Collection can contain resource items.
   *
   * @param CollectionInterface $collection
   *   A Collection entity.
   *
   * @return boolean
   *   TRUE if the Collection can collect items that are marked as 'is_resource_item'.
   */
  public function collectionCanContainResourceItems(CollectionInterface $collection): bool {
    /** @var \Drupal\collection\Entity\CollectionTypeInterface $collection_type */
    $collection_type = $collection->type->entity;
    $allowed_collection_item_type_ids = $collection_type->getAllowedCollectionItemTypes(NULL, NULL, $collection);
    $allowed_collection_item_types = $this->entityTypeManager->getStorage('collection_item_type')->loadMultiple($allowed_collection_item_type_ids);

    /** @var \Drupal\collection\Entity\CollectionItemTypeInterface $allowed_collection_item_type */
    foreach ($allowed_collection_item_types as $allowed_collection_item_type) {
      if ($allowed_collection_item_type->getThirdPartySetting('collection_resource_library', 'is_resource_item')) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
