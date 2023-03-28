<?php

namespace Drupal\ilr;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Entity reference list for a computed field that displays classes.
 */
class CourseClassItemList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * Compute the list of all future classes for this course.
   */
  protected function computeValue() {
    $course_entity = $this->getEntity();

    $query = \Drupal::entityQuery('node')
      ->condition('status', 1)
      ->condition('type', 'class')
      ->condition('field_course', $course_entity->id())
      ->sort('field_date_start');

    // Limit the query to classes that start tomorrow or later.
    $midnight_tonight = new DrupalDateTime('today 23:59');
    $query->condition('field_date_start', $midnight_tonight->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT), '>=');

    $results = $query->execute();
    $key = 0;

    foreach ($results as $nid) {
      $this->list[$key] = $this->createItem($key, $nid);
      $key++;
    }
  }

}
