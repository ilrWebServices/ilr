<?php

namespace Drupal\person\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\person\PersonaTypeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

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
 *   admin_permission = "administer person fields and persona types",
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
 *   },
 *   config_export = {
 *     "id" = "id",
 *     "label" = "label"
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
   *
   * Add custom inheritable Person fields to new Persona types.
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    if ($update || \Drupal::isConfigSyncing()) {
      return;
    }

    $entity_type_manager = $this->entityTypeManager();
    $entity_field_manager = $this->entityFieldManager();
    $display_repository = \Drupal::service('entity_display.repository');
    $fieldStorageConfigStorage = $entity_type_manager->getStorage('field_storage_config');
    $fieldConfigStorage = $entity_type_manager->getStorage('field_config');
    $person_form_display = $display_repository->getFormDisplay('person', 'person');
    $person_view_display = $display_repository->getViewDisplay('person', 'person');
    $persona_form_display = $display_repository->getFormDisplay('persona', $this->id());
    $persona_view_display = $display_repository->getViewDisplay('persona', $this->id());

    // Check all of the inheritable Person fields to see if this Persona type
    // has the storage and config.
    foreach ($entity_field_manager->getFieldDefinitions('person', 'person') as $person_field_name => $person_field_def) {
      // `FieldConfig` definitions are user created fields (i.e. `field_*`).
      // This filters out base fields, which would be `BaseFieldDefinition`s.
      if ($person_field_def instanceof FieldConfig) {
        /** @var \Drupal\field\Entity\FieldStorageConfig $person_field_storage */
        $person_field_storage_config = $person_field_def->getFieldStorageDefinition();

        // Create the FieldStorageConfig for all Persona types if missing. This
        // is the 'base field' for all Persona types and is only necessary if
        // this is an new field that no Persona types have yet.
        $persona_field_storage_config = FieldStorageConfig::loadByName('persona', $person_field_name);
        if (empty($persona_field_storage_config)) {
          $persona_field_storage_config = $fieldStorageConfigStorage->create([
            'field_name' => $person_field_name,
            'entity_type' => 'persona',
            'type' => $person_field_storage_config->getType(),
            'settings' => $person_field_storage_config->getSettings(),
          ]);
          $persona_field_storage_config->save();
        }

        // Create the FieldConfig for this Persona type if missing. This is the
        // 'field instance' for this type.
        $persona_field_config = FieldConfig::loadByName('persona', $this->id(), $person_field_name);
        if (empty($persona_field_config)) {
          $persona_field_config = $fieldConfigStorage->create([
            'field_storage' => $persona_field_storage_config,
            'bundle' => $this->id(),
            'label' => $person_field_def->getLabel(),
            'settings' => $person_field_def->getSettings(),
          ]);
          $persona_field_config->save();
        }

        // Configure form and view modes to match Person fields, too.
        $persona_form_display->setComponent($person_field_name, $person_form_display->getComponent($person_field_name))->save();
        $persona_view_display->setComponent($person_field_name, $person_view_display->getComponent($person_field_name))->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getInheritedFieldNames() {
    $entity_field_manager = $this->entityFieldManager();
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

  /**
   * Gets the entity field manager.
   *
   * @return \Drupal\Core\Entity\EntityFieldManager
   *   The entity field manager service.
   */
  protected function entityFieldManager() {
    return \Drupal::service('entity_field.manager');
  }

}
