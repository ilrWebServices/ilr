<?php

namespace Drupal\ilr\Entity;

use Drupal\Core\StringTranslation\StringTranslationTrait;

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
        case 'Online (Synchronous)':
          $delivery_method = 'In Person';
          break;

        case 'Online (Date Driven)':
          $delivery_method = 'Online';
          break;

        case 'On Demand/Self Paced':
          $delivery_method = 'On Demand';
          break;
      }
    }

    return $delivery_method;
  }
}
