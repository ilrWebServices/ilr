<?php

namespace Drupal\person\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
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
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\person\PersonListBuilder",
 *     "form" = {
 *       "default" = "Drupal\person\Form\PersonForm",
 *       "add" = "Drupal\person\Form\PersonForm",
 *       "edit" = "Drupal\person\Form\PersonForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer content",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "canonical" = "/person/{person}",
 *     "add-form" = "/person/add",
 *     "edit-form" = "/person/{person}/edit",
 *     "delete-form" = "/person/{person}/delete",
 *     "collection" = "/admin/content/people",
 *   },
 * )
 */
class Person extends ContentEntityBase implements ContentEntityInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
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
      ->setDisplayConfigurable('view', TRUE);

    $fields['first_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('First Name'))
      ->setDescription(t('The first name of this Person.'))
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
      ->setDisplayConfigurable('view', TRUE);

    $fields['last_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Last Name'))
      ->setDescription(t('The last name of this Person, used for sorting.'))
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
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
