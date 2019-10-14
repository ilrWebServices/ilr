<?php

namespace Drupal\ilr;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Entity reference list for a computed field that displays certificates.
 */
class CourseCertificateItemList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   *
   */
  protected function computeValue() {
    $course_entity = $this->getEntity();

    $query = \Drupal::entityQuery('node')
      ->condition('status', 1)
      ->condition('type', 'certificate')
      ->condition('field_course', $course_entity->id());

    $results = $query->execute();
    $key = 0;

    foreach ($results as $nid) {
      $this->list[$key] = $this->createItem($key, $nid);
      $key++;
    }
  }

}
