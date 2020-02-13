<?php

namespace Drupal\person\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\person\PersonaTypeInterface;

/**
 * Defines the Persona type entity.
 *
 * @ConfigEntityType(
 *   id = "persona_type",
 *   label = @Translation("Persona type"),
 *   label_collection = @Translation("Persona types"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\person\PersonaTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\person\Form\PersonaTypeForm",
 *       "edit" = "Drupal\person\Form\PersonaTypeForm",
 *       "delete" = "Drupal\person\Form\PersonaTypeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "persona_type",
 *   admin_permission = "administer site configuration",
 *   bundle_of = "persona",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/persona/add",
 *     "edit-form" = "/admin/structure/persona/{persona_type}",
 *     "delete-form" = "/admin/structure/persona/{persona_type}/delete",
 *     "collection" = "/admin/structure/persona"
 *   }
 * )
 */

class PersonaType extends ConfigEntityBundleBase implements PersonaTypeInterface {

  /**
   * The Persona type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Persona type label.
   *
   * @var string
   */
  protected $label;

  /**
   * {@inheritdoc}
   */
  public function getInheritedFieldNames() {
    $entity_field_manager = \Drupal::service('entity_field.manager');
    $field_names = [];
    $person_field_defs = $entity_field_manager->getFieldDefinitions('person', 'person');
    $persona_field_defs = $entity_field_manager->getFieldDefinitions('persona', $this->id());

    foreach ($person_field_defs as $person_field_name => $person_field_def) {
      // Only the display_name and Field API fields can possibly be inherited.
      if ($person_field_name === 'display_name' || strpos($person_field_name, 'field_') === 0) {
        // To inherit a value, this Persona must have a field of the same name,
        // type, and settings, and it must not be required.
        if (isset($persona_field_defs[$person_field_name])) {
          $persona_field_def = $persona_field_defs[$person_field_name];
          $types_match = $person_field_def->getType() === $persona_field_def->getType();
          $settings_match = $person_field_def->getSettings() === $persona_field_def->getSettings();

          if ($types_match && $settings_match && !$persona_field_def->isRequired()) {
            $field_names[] = $person_field_name;
          }
        }
      }
    }

    return $field_names;
  }

}
