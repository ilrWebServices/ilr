<?php

namespace Drupal\ilr\Entity;

class EventLandingNode extends EventNodeBase implements EventNodeInterface {

  /**
   * Gets a string representation of the event delivery method.
   *
   * @return string
   *  A translated string.
   */
  public function deliveryMethod():string {
    if ($this->hasField('field_delivery_method')) {
      $delivery_method = $this->field_delivery_method->value;

      switch ($delivery_method) {
        case 'In Person':
          $delivery_method = 'In Person';
          break;
        case 'Online (Synchronous)':
          $delivery_method = 'Online';
          break;
        case 'Online (Date Driven)':
          $delivery_method = 'Online Series';
          break;
        case 'On Demand/Self Paced':
          $delivery_method = 'On Demand';
          break;
      }
    }

    if (empty($delivery_method)) {
      if (!$this->location_address->isEmpty()) {
        $delivery_method = 'In Person';
      }

      if (!$this->location_link->isEmpty()) {
        $delivery_method = 'Online';
      }
    }

    return $delivery_method ?? 'Online';
  }
}
