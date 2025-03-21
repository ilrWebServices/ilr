<?php

namespace Drupal\ilr\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Returns courses as a json feed.
 */
class CourseJsonFeed extends ControllerBase {

  /**
   * The `content` route responds with the feed items.
   */
  public function content(): JsonResponse {
    $data = ['courses' => []];
    $response = new JsonResponse($data, $status = 200, $headers = []);
    $node_storage = $this->entityTypeManager()->getStorage('node');

    // Limit to the following topics terms:
    // Human Resources (7), Leadership Development (8), Employee Relations
    // and Investigations (3), IDEA (18), Labor Relations (5).

    $course_query = $node_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'course')
      ->condition('status', 1)
      ->sort('title', 'ASC')
      ->condition('field_topics', [7, 8, 3, 18, 5], 'IN');

    $course_ids = $course_query->execute();

    if (empty($course_ids)) {
      return $response;
    }

    $courses = $node_storage->loadMultiple($course_ids);

    foreach ($courses as $course) {
      // Remove courses without upcoming classes.
      if (count($course->classes) === 0) {
        continue;
      }

      $course_data = [
        'uuid' => $course->uuid(),
        'title' => $course->label(),
        'description' => $course->body->value,
        'key_takeaways' => $course->field_key_outcomes->value,
        'audiences' => $course->field_target_audience->value,
        'topics' => [],
        'sessions' => [],
      ];

      foreach ($course->field_topics->referencedEntities() as $topic) {
        $course_data['topics'][] = $topic->label();
      }

      foreach ($course->classes->referencedEntities() as $class) {
        $class_start = new DrupalDateTime($class->field_date_start->value, DateTimeItemInterface::STORAGE_TIMEZONE);
        $class_end = new DrupalDateTime($class->field_date_end->value, DateTimeItemInterface::STORAGE_TIMEZONE);
        $register_url = $course->toUrl('canonical', ['absolute' => TRUE]);
        $delivery_method = '';
        $location = '';

        if ($class->field_delivery_method->value === 'Classroom' && !$class->field_address->isEmpty()) {
          $location = strtr("%address_line1\n%city, %state", [
            '%address_line1' => $class->field_address->address_line1,
            '%city' => $class->field_address->locality,
            '%state' => $class->field_address->administrative_area,
          ]);
          $delivery_method = 'In person';
        }
        else {
          continue;
        }

        // Even if this class is in person, we still only want NYC.
        if ($class->field_address->locality !== 'New York') {
          continue;
        }

        if ($mapped_object = $class->getClassNodeSalesforceMappedObject()) {
          $register_url = \Drupal::configFactory()->get('ilr_registration_system.settings')->get('url') . $mapped_object->salesforce_id->getString();
        }

        if (!$class->field_external_link->isEmpty()) {
          $register_url = $class->field_external_link->uri;
        }

        $course_data['sessions'][] = [
          'start' => $class_start->format('c'),
          'end' => $class_end->format('c'),
          'location' => $location,
          'delivery_type' => $delivery_method,
          'price' => '$' . $class->field_price->value,
          'discount_price' => '$' . ($class->ilroutreach_discount_price->value ?? $class->field_price->value),
          'discount_enddate' => $class->ilroutreach_discount_date->end_value,
          'registration_url' => $register_url,
        ];
      }

      if (!empty($course_data['sessions'])) {
        $data['courses'][] = $course_data;
      }
    }

    $response->setData($data);
    return $response;
  }

}
