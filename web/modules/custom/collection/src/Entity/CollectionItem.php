<?php

namespace Drupal\collection\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Collection item entity.
 *
 * @ingroup collection
 *
 * @ContentEntityType(
 *   id = "collection_item",
 *   label = @Translation("Collection item"),
 *   label_collection = @Translation("Collection items"),
 *   bundle_label = @Translation("Collection item type"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\collection\CollectionItemListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\collection\Form\CollectionItemForm",
 *       "add" = "Drupal\collection\Form\CollectionItemForm",
 *       "edit" = "Drupal\collection\Form\CollectionItemForm",
 *       "delete" = "Drupal\collection\Form\CollectionItemDeleteForm",
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\collection\CollectionItemRouteProvider",
 *     },
 *     "access" = "Drupal\collection\CollectionAccessControlHandler",
 *   },
 *   base_table = "collection_item",
 *   data_table = "collection_item_field_data",
 *   admin_permission = "administer collections",
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "type",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *   },
 *   links = {
 *     "canonical" = "/collection/{collection}/items/{collection_item}",
 *     "add-page" = "/collection/{collection}/items/add",
 *     "add-form" = "/collection/{collection}/items/add/{collection_item_type}",
 *     "edit-form" = "/collection/{collection}/items/{collection_item}/edit",
 *     "delete-form" = "/collection/{collection}/items/{collection_item}/delete",
 *     "collection" = "/collection/{collection}/items",
 *   },
 *   bundle_entity_type = "collection_item_type",
 *   field_ui_base_route = "entity.collection_item_type.edit_form",
 *   constraints = {
 *     "UniqueItem" = {}
 *   }
 * )
 */
class CollectionItem extends ContentEntityBase implements CollectionItemInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);
    $uri_route_parameters['collection'] = $this->get('collection')->target_id;
    return $uri_route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Check for an existing collection item.
    if ($this->collection->entity->getItem($this->item->entity)) {
      throw new \LogicException('Collection already has this entity.');
    }

    // Automatically update the name of this collection item to a combination of
    // the collection and the item.
    $collection_label = $this->collection->entity->label();
    $item_label = $this->item->entity->label();

    // TODO: Possibly truncate this name to the length of the field.
    $this->set('name', $collection_label . ' - ' . $item_label);
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * Returns \Drupal\Core\TypedData\TypedDataInterface
   */
  public function getAttribute(string $key) {
    foreach ($this->attributes as $attribute) {
      if ($attribute->key === $key) {
        return $attribute;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * Returns \Drupal\Core\TypedData\TypedDataInterface
   */
  public function setAttribute(string $key, string $value) {
    // Update existing attribute.
    if ($attribute = $this->getAttribute($key)) {
      $attribute->value = $value;
      return $attribute;
    }

    // Add new attribute.
    return $this->attributes->appendItem([
      'key' => $key,
      'value' => $value,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Owner'))
      ->setDescription(t('The user that owns this Collection item.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the collection item. This should be autogenerated and is for admin use only.'))
      ->setSettings([
        'max_length' => 200,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'hidden',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['collection'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Collection'))
      ->setDescription(t('The collection to which this item belongs.'))
      ->setSetting('target_type', 'collection')
      ->setSetting('handler', 'default:collection')
      ->setDefaultValueCallback(static::class . '::getCollectionParam')
      ->setCardinality(1)
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 0,
        'settings' => ['link' => TRUE]
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['item'] = BaseFieldDefinition::create('dynamic_entity_reference')
      ->setLabel(t('Collected item'))
      ->setDescription(t('The item for the collection.'))
      ->setSetting('exclude_entity_types', FALSE)
      ->setSetting('entity_type_ids', [
        'menu' => 'menu',
        'node' => 'node',
        'user' => 'user'
      ])
      ->setCardinality(1)
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'dynamic_entity_reference_label',
        'settings' => ['link' => TRUE],
      ])
      ->setDisplayOptions('form', [
        'type' => 'dynamic_entity_reference_default',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['attributes'] = BaseFieldDefinition::create('key_value')
      ->setLabel(t('Attributes'))
      ->setCardinality(\Drupal\Core\Field\FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setSetting('key_max_length', 255)
      ->setSetting('max_length', 255)
      ->setSetting('key_is_ascii', FALSE)
      ->setSetting('is_ascii', FALSE)
      ->setSetting('case_sensitive', FALSE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the collection item was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the collection item was last edited.'));

    return $fields;
  }

  /**
   * Returns the default value for the collection field.
   *
   * @return int
   *   The entity id of the collection in the current route.
   */
  public static function getCollectionParam() {
    return \Drupal::routeMatch()->getRawParameter('collection');
  }

}
