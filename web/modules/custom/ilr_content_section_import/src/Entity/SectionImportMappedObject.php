<?php

namespace Drupal\ilr_content_section_import\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the `section_import_mapped_object` entity.
 *
 * @ContentEntityType(
 *   id = "section_import_mapped_object",
 *   label = @Translation("Section Import Mapped Object"),
 *   base_table = "section_import_mapped_object",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 * )
 */
class SectionImportMappedObject extends ContentEntityBase implements ContentEntityInterface {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the SectionImportMappedObject entity.'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the SectionImportMappedObject entity.'))
      ->setReadOnly(TRUE);

    $fields['type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity type'))
      ->setDescription(t('The entity type of the imported entity.'))
      ->setReadOnly(TRUE);

    $fields['sourceid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Source ID'))
      ->setDescription(t('The source ID of the imported entity.'))
      ->setReadOnly(TRUE);

    $fields['destid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Destination ID'))
      ->setDescription(t('The destination ID of the imported entity.'))
      ->setReadOnly(TRUE);

    return $fields;
  }

}
