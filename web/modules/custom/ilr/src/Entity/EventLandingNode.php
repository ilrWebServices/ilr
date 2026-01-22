<?php

namespace Drupal\ilr\Entity;

class EventLandingNode extends EventNodeBase implements EventNodeInterface {

  /**
   * Gets a string representation of the event delivery method.
   *
   * @return string
   *   The delivery method.
   */
  public function deliveryMethod():string {
    if ($this->hasField('field_delivery_method')) {
      $delivery_method = $this->field_delivery_method->value;

      // Normalize certain values from SalesForce, but do not provide a default
      // (that way, we won't accidentally override a saved field value).
      switch ($delivery_method) {
        case 'In Person':
          $delivery_method = 'In Person';
          break;

        case 'Online (Synchronous)':
        case 'Online (Date Driven)':
          $delivery_method = 'Online';
          break;

        case 'On Demand/Self Paced':
          $delivery_method = 'On Demand';
          break;
      }
    }

    // If still no value, then check the address field.
    if (empty($delivery_method)) {
      if (!$this->location_address->isEmpty()) {
        $delivery_method = 'In Person';
      }
    }

    return $delivery_method ?? 'Online';
  }

}
