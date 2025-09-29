<?php

namespace Drupal\person\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'persona_name_list' formatter.
 *
 * @FieldFormatter(
 *   id = "persona_name_list",
 *   label = @Translation("Persona name list"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class PersonaNameListFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = [];

    // Fall back to field settings by default.
    $settings['prefix'] = '';

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prefix for the list of persona names, e.g. "by".'),
      '#default_value' => $this->getSetting('prefix'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $setting = $this->getSetting('prefix');

    if ($setting) {
      $summary[] = $this->t('Prefix: @prefix', [
        '@prefix' => $this->getSetting('prefix'),
      ]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    $persona_display_names = [];

    foreach ($items as $delta => $persona) {
      $persona_display_names[] = $persona->entity->getDisplayName() ?? 'Unknown';
    }

    if (!empty($persona_display_names)) {
      $element[0] = [
        '#theme' => 'persona_name_list',
        '#names' => $persona_display_names,
        '#prefix' => $this->getSetting('prefix'),
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * This widget only supports multiple persona entity reference fields.
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $cardinality = $field_definition->getFieldStorageDefinition()->getCardinality();
    $target_type = $field_definition->getFieldStorageDefinition()->getSetting('target_type');
    return ($target_type === 'persona' && $cardinality !== 1);
  }

}
