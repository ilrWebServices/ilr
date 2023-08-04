<?php

namespace Drupal\collection_tools;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\taxonomy\VocabularyInterface;

trait CollectionVocabularyTrait {

  /**
   * Get a collected vocabulary with a given attribute key.
   *
   * @param CollectionInterface $collection
   *   A Collection entity.
   * @param string $key
   *   The name of a collection item entity attribute key.
   *
   * @return VocabularyInterface|boolean
   *   A Vocabulary entity or FALSE.
   */
  public function getCollectedVocabularyByKey(CollectionInterface $collection, string $key): VocabularyInterface|bool {
    $collection_items = $collection->findItems('taxonomy_vocabulary');

    foreach ($collection_items as $collection_item) {
      if ($collection_item->getAttribute($key) !== FALSE) {
        return $collection_item->item->entity;
      }
    }

    return FALSE;
  }

}
