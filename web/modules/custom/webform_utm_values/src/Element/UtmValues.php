<?php

namespace Drupal\webform_utm_values\Element;

use Drupal\webform\Element\WebformCompositeBase;
use Drupal\webform_utm_values\Plugin\WebformElement\UtmValues as UtmValuesWebformElement;

/**
 * Provides a 'utm_values' webform element.
 *
 * @FormElement("utm_values")
 */
class UtmValues extends WebformCompositeBase {

  /**
   * {@inheritdoc}
   */
  public static function getCompositeElements(array $element) {
    $elements = [];

    foreach (UtmValuesWebformElement::$utmFields as $field) {
      $elements[$field] = [
        '#type' => 'value',
        '#title' => $field,
      ];
    }

    return $elements;
  }

}
