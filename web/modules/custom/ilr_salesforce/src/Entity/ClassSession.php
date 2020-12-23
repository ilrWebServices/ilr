<?php

namespace Drupal\ilr_salesforce\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines the Class Session entity.
 *
 * @ingroup ilr_salesforce
 *
 * @ContentEntityType(
 *   id = "class_session",
 *   label = @Translation("Class session"),
 *   base_table = "class_session",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "title",
 *   },
 * )
 */
class ClassSession extends ContentEntityBase implements ContentEntityInterface {

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->get('title')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle($title) {
    $this->set('title', $title);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the Class Session entity.'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the Class Session entity.'))
      ->setReadOnly(TRUE);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Session title'))
      ->setDescription(t('The title given to this session.'))
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('form', [
        'type' => 'string',
        'weight' => 0,
      ]);

    $fields['class'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Class'))
      ->setDescription(t('The class to which this session belongs.'))
      ->setSetting('target_type', 'node');

    $fields['session_date'] = BaseFieldDefinition::create('daterange')
      ->setLabel(t('Date and time'))
      ->setDescription(t('The start and end date and time.'))
      ->setSettings([
        'datetime_type' => 'datetime',
      ])
      ->setDisplayOptions('form', [
        'type' => 'daterange_default',
        'weight' => 1,
      ])
      ->setDisplayOptions('view', [
        'type' => 'daterange_custom',
        'weight' => 1,
        'label' => 'hidden',
        'settings' => [
          'timezone_override' => '',
          'date_format' => 'g:i a T',
          'separator' => '-',
        ],
      ]);

    $fields['address'] = BaseFieldDefinition::create('address')
      ->setLabel(t('Address'))
      ->setDescription(t('Address for the session.'));

    return $fields;
  }

}
