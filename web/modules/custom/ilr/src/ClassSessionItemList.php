<?php

namespace Drupal\ilr;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Entity reference list for a computed field that displays class sessions.
 */
class ClassSessionItemList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * Query all the class_sessions for this class entity.
   */
  protected function computeValue() {
    $class_entity = $this->getEntity();
    $query = \Drupal::entityQuery('class_session')
      ->accessCheck(TRUE)
      ->condition('class', $class_entity->id())
      ->sort('session_date__value');

    $results = $query->execute();
    $key = 0;

    foreach ($results as $id) {
      $this->list[$key] = $this->createItem($key, $id);
      $key++;
    }
  }

}
