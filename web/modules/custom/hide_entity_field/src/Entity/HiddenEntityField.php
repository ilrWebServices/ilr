<?php

namespace Drupal\hide_entity_field\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the `section_import_mapped_object` entity.
 *
 * @ContentEntityType(
 *   id = "hidden_entity_field",
 *   label = @Translation("Hidden Entity Field"),
 *   base_table = "hidden_entity_field",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 * )
 */
class HiddenEntityField extends ContentEntityBase implements ContentEntityInterface {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the HiddenEntityField entity.'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the HiddenEntityField entity.'))
      ->setReadOnly(TRUE);

    // Reference to entity.
    $fields['entity'] = BaseFieldDefinition::create('dynamic_entity_reference')
      ->setLabel(t('Entity'))
      ->setDescription(t('The entity with the hidden field.'))
      ->setSetting('exclude_entity_types', FALSE)
      ->setSetting('entity_type_ids', [
        'node' => 'node',
      ])
      ->setCardinality(1)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'dynamic_entity_reference_label',
        'settings' => ['link' => TRUE],
      ])
      ->setDisplayOptions('form', [
        'type' => 'dynamic_entity_reference_default',
        'weight' => 5,
      ])
      ->setRequired(TRUE);

    $fields['field_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Field name'))
      ->setDescription(t('Machine name of the field to hide.'))
      ->setRequired(TRUE);

    return $fields;
  }

}
