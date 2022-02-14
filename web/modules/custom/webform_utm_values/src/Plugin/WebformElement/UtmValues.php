<?php

namespace Drupal\webform_utm_values\Plugin\WebformElement;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformElement\WebformCompositeBase;

/**
 * Provides a 'utm_values' element.
 *
 * @WebformElement(
 *   id = "utm_values",
 *   label = @Translation("UTM values"),
 *   description = @Translation("Provides a form element to populate hidden UTM field values from a cookie."),
 *   category = @Translation("Advanced elements"),
 *   composite = TRUE,
 *   states_wrapper = TRUE,
 * )
 */
class UtmValues extends WebformCompositeBase {

  /**
   * List of available UTM fields.
   *
   * @var string[]
   */
  public static $utmFields = [
    'utm_source',
    'utm_medium',
    'utm_campaign',
    'utm_term',
    'utm_content',
  ];

  /**
   * {@inheritdoc}
   */
  protected function defineDefaultProperties() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCompositeElements() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function initializeCompositeElements(array &$element) {
    $element['#webform_composite_elements'] = [];

    foreach (self::$utmFields as $field_name) {
      $element['#webform_composite_elements'][$field_name] = [
        '#title' => $this->t($field_name),
        '#type' => 'textfield',
        '#maxlength' => 255,
      ];
     }
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $form['composite']['element']['#header']['visible'] = $this->t('Enabled');
    return $form;
  }

}
