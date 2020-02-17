<?php

namespace Drupal\person\Entity;

use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\person\PersonaInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the Persona entity.
 *
 * @ingroup person
 *
 * @ContentEntityType(
 *   id = "persona",
 *   label = @Translation("Persona"),
 *   label_collection = @Translation("Personas"),
 *   label_singular = @Translation("persona"),
 *   label_plural = @Translation("personas"),
 *   label_count = @PluralTranslation(
 *     singular = "@count persona",
 *     plural = "@count personas"
 *   ),
 *   bundle_label = @Translation("Persona type"),
 *   base_table = "persona",
 *   revision_table = "persona_revision",
 *   show_revision_ui = TRUE,
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\person\PersonaListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\person\Form\PersonaForm",
 *       "add" = "Drupal\person\Form\PersonaForm",
 *       "edit" = "Drupal\person\Form\PersonaForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\person\Routing\PersonaRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer content",
 *   entity_keys = {
 *     "id" = "pid",
 *     "revision" = "vid",
 *     "label" = "admin_label",
 *     "uuid" = "uuid",
 *     "published" = "status",
 *     "bundle" = "type",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log_message",
 *   },
 *   links = {
 *     "canonical" = "/person/{person}/persona/{persona}",
 *     "add-page" = "/person/{person}/persona/add",
 *     "add-form" = "/person/{person}/persona/add/{persona_type}",
 *     "edit-form" = "/person/{person}/persona/{persona}/edit",
 *     "delete-form" = "/person/{person}/persona/{persona}/delete",
 *     "collection" = "/person/{person}/personas",
 *   },
 *   bundle_entity_type = "persona_type",
 *   field_ui_base_route = "entity.persona_type.edit_form",
 * )
 */
class Persona extends EditorialContentEntityBase implements PersonaInterface {

  /**
   * {@inheritdoc}
   */
  public function getDisplayName() {
    $display_name = $this->display_name->value;
    $display_name = \Drupal::moduleHandler()->alter('persona_display_name', $display_name, $this);
    return $display_name;
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);
    $uri_route_parameters['person'] = $this->person->target_id;
    return $uri_route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    foreach ($this->type->entity->getInheritedFieldNames() as $field_name) {
      if ($this->$field_name->isEmpty()) {
        $this->$field_name = $this->person->entity->$field_name;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preSaveRevision(EntityStorageInterface $storage, \stdClass $record) {
    parent::preSaveRevision($storage, $record);

    $is_new_revision = $this->isNewRevision();
    if (!$is_new_revision && isset($this->original) && empty($record->revision_log_message)) {
      // If we are updating an existing persona without adding a new revision,
      // we need to make sure $entity->revision_log_message is reset whenever it
      // is empty. Therefore, this code allows us to avoid clobbering an
      // existing log entry with an empty one.
      $record->revision_log_message = $this->original->revision_log_message->value;
    }

    if ($is_new_revision) {
      $record->revision_created = self::getRequestTime();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function fieldIsOverridden($field_name) {
    if (!in_array($field_name, $this->type->entity->getInheritedFieldNames()) || $this->person->isEmpty()) {
      return FALSE;
    }

    // `array_filter` is used to trim any empty items in the list. This appears
    // to happen when `EntityForm`s add an extra item to multi-value fields.
    return $this->person->entity->$field_name->getValue() !== array_filter($this->$field_name->getValue());
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['status']
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['person'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Person'))
      ->setDescription(t('The person represented by this persona.'))
      ->setSetting('target_type', 'person')
      ->setSetting('handler', 'default:person')
      ->setDefaultValueCallback(static::class . '::getPersonParam')
      ->setCardinality(1)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => -100,
      ])
      ->setRequired(TRUE);

    $fields['admin_label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Admin Label'))
      ->setDescription(t('A label for this Persona used to distinguish it from similar Personas (e.g. Jane Doe - Department Staffer).'))
      ->setSettings([
        'default_value' => '',
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setRevisionable(TRUE)
      ->setRequired(TRUE);

    $fields['display_name'] = BaseFieldDefinition::create('string')
        ->setLabel(t('Display Name'))
        ->setDescription(t('Generally, the full name of this Persona, suitable for display.'))
        ->setSettings([
          'default_value' => '',
          'max_length' => 255,
          'text_processing' => 0,
        ])
        ->setDisplayOptions('view', [
          'label' => 'hidden',
          'type' => 'string',
          'weight' => 0,
        ])
        ->setDisplayOptions('form', [
          'type' => 'string_textfield',
          'weight' => 0,
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayConfigurable('view', TRUE)
        ->setRevisionable(TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time this Persona was last edited.'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function getRequestTime() {
    return \Drupal::time()->getRequestTime();
  }

  /**
   * Returns the default value for the person field.
   *
   * @return int
   *   The entity id of the collection in the current route.
   */
  public static function getPersonParam() {
    return \Drupal::routeMatch()->getRawParameter('person');
  }

}
