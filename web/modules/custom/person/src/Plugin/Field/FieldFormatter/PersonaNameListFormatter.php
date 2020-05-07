<?php

namespace Drupal\person\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

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
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    $persona_display_names = [];

    foreach ($items as $delta => $persona) {
      $persona_display_names[] = $persona->entity->getDisplayName();
    }

    if (!empty($persona_display_names)) {
      $element = [
        '#type' => 'inline_template',
        '#template' => '<div class="persona-display-name-list">{{names}}</div>',
        '#context' => ['names' => implode(', ', $persona_display_names)],
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
