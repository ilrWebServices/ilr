<?php

namespace Drupal\person\Entity;

use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\person\PersonInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the Person entity.
 *
 * @ingroup person
 *
 * @ContentEntityType(
 *   id = "person",
 *   label = @Translation("Person"),
 *   label_collection = @Translation("People"),
 *   label_singular = @Translation("person"),
 *   label_plural = @Translation("people"),
 *   label_count = @PluralTranslation(
 *     singular = "@count person",
 *     plural = "@count people"
 *   ),
 *   base_table = "person",
 *   revision_table = "person_revision",
 *   show_revision_ui = TRUE,
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\person\PersonListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\person\Form\PersonForm",
 *       "add" = "Drupal\person\Form\PersonForm",
 *       "edit" = "Drupal\person\Form\PersonForm",
 *       "delete" = "Drupal\person\Form\PersonDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer persons",
 *   entity_keys = {
 *     "id" = "pid",
 *     "revision" = "vid",
 *     "label" = "display_name",
 *     "uuid" = "uuid",
 *     "published" = "status",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log_message",
 *   },
 *   links = {
 *     "canonical" = "/person/{person}",
 *     "add-form" = "/person/add",
 *     "edit-form" = "/person/{person}/edit",
 *     "delete-form" = "/person/{person}/delete",
 *     "collection" = "/admin/content/people",
 *     "revision" = "/person/{person}/revisions/{person_revision}/view",
 *   },
 *   field_ui_base_route = "entity.person.admin_form",
 * )
 */
class Person extends EditorialContentEntityBase implements PersonInterface {

  /**
   * {@inheritdoc}
   */
  public function preSaveRevision(EntityStorageInterface $storage, \stdClass $record) {
    parent::preSaveRevision($storage, $record);

    $is_new_revision = $this->isNewRevision();
    if (!$is_new_revision && isset($this->original) && empty($record->revision_log_message)) {
      // If we are updating an existing person without adding a new revision, we
      // need to make sure $entity->revision_log_message is reset whenever it is
      // empty. Therefore, this code allows us to avoid clobbering an existing
      // log entry with an empty one.
      $record->revision_log_message = $this->original->revision_log_message->value;
    }

    if ($is_new_revision) {
      $record->revision_created = self::getRequestTime();
    }
  }

  /**
   * {@inheritdoc}
   *
   * Update inherited Persona fields when saving an existing Person.
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    if ($update === FALSE) {
      return;
    }

    // Load all personas for this person.
    $personas = $this->entityTypeManager()->getStorage('persona')->loadByProperties([
      'person' => $this->id(),
    ]);

    foreach ($personas as $persona) {
      $needs_save = FALSE;

      foreach ($persona->type->entity->getInheritedFieldNames() as $field_name) {
        $original_value = $this->original->$field_name->getValue();

        // Check if the value of this inherited Person field is changing.
        if ($original_value !== $this->$field_name->getValue()) {
          // Check if the value of the Persona field was inherited (i.e. has the
          // same value as the original Person field value).
          if ($persona->$field_name->getValue() === $original_value) {
            $persona->$field_name = $this->$field_name;
            $needs_save = TRUE;
          }
        }
      }

      if ($needs_save) {
        $persona->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    $persona_ids = \Drupal::entityQuery('persona')
      ->accessCheck(TRUE)
      ->condition('person', array_keys($entities), 'IN')
      ->execute();

    $persona_storage = \Drupal::service('entity_type.manager')->getStorage('persona');
    $personas = $persona_storage->loadMultiple($persona_ids);
    $persona_storage->delete($personas);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['display_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Display Name'))
      ->setDescription(t('Generally, the full name of this Person, suitable for display.'))
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
      ->setDescription(t('The time this Person was last edited.'))
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

}
