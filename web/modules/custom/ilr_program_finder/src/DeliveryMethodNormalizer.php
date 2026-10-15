<?php

namespace Drupal\ilr_program_finder;

trait DeliveryMethodNormalizer {

  /**
   * Return all normalized delivery methods for a given string.
   *
   * The incoming delivery method is most likely coming from a source where we
   * can't change the options.
   *
   * The following values are possible from Salesforce:
   * - Classroom
   * - In Person (Not Classroom)
   * - On Demand/Self Paced
   * - Online (Date Driven)
   * - Online (Synchronous)
   *
   * Some examples from event_landing_pages:
   * - Hybrid
   * - In Person
   * - In-person
   * - Live-Virtual
   * - Online
   *
   * @param string $delivery_method
   *
   * @return string[]
   */
  public function getNormalizedDeliveryMethods(?string $delivery_method, string $default_value = 'Online'): array {
    $delivery_methods = [];

    switch ($delivery_method) {
      case 'Classroom':
      case 'In Person (Not Classroom)':
        $delivery_methods[] = 'In Person';
        break;
      case 'Online':
        $delivery_methods[] = 'Online';
        break;
      case 'Online (Synchronous)':
        $delivery_methods[] = 'Online';
        $delivery_methods[] = 'Live';
        break;
      case 'Live-Virtual':
        $delivery_methods[] = 'Online';
        $delivery_methods[] = 'Live';
        break;
      case 'Hybrid':
        $delivery_methods[] = 'In Person';
        $delivery_methods[] = 'Online';
        $delivery_methods[] = 'Live';
        break;
      case 'In Person':
      case 'In-person':
        $delivery_methods[] = 'In Person';
        break;
      default:
        $delivery_methods[] = $default_value;
    }

    return $delivery_methods;
  }

}
