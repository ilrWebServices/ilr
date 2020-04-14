<?php

namespace Drupal\collection\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;
use Drupal\collection\Event\CollectionEvents;
use Drupal\collection\Event\CollectionCreateEvent;
use Drupal\collection\Event\CollectionUpdateEvent;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Defines the Collection entity.
 *
 * @ingroup collection
 *
 * @ContentEntityType(
 *   id = "collection",
 *   label = @Translation("Collection"),
 *   label_singular = @Translation("collection"),
 *   label_plural = @Translation("collections"),
 *   label_count = @PluralTranslation(
 *     singular = "@count collection",
 *     plural = "@count collections"
 *   ),
 *   label_collection = @Translation("Collections"),
 *   bundle_label = @Translation("Collection type"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\collection\CollectionListBuilder",
 *     "views_data" = "Drupal\collection\Entity\CollectionViewsData",
 *     "translation" = "Drupal\collection\CollectionTranslationHandler",
 *     "form" = {
 *       "default" = "Drupal\collection\Form\CollectionForm",
 *       "add" = "Drupal\collection\Form\CollectionForm",
 *       "edit" = "Drupal\collection\Form\CollectionForm",
 *       "delete" = "Drupal\collection\Form\CollectionDeleteForm",
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\collection\CollectionRouteProvider",
 *     },
 *     "access" = "Drupal\collection\CollectionAccessControlHandler",
 *   },
 *   base_table = "collection",
 *   data_table = "collection_field_data",
 *   revision_table = "collection_revision",
 *   revision_data_table = "collection_field_revision",
 *   show_revision_ui = TRUE,
 *   translatable = TRUE,
 *   admin_permission = "administer collections",
 *   entity_keys = {
 *     "id" = "cid",
 *     "revision" = "vid",
 *     "bundle" = "type",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "status" = "status",
 *     "published" = "status",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log_message",
 *   },
 *   links = {
 *     "canonical" = "/collection/{collection}",
 *     "add-page" = "/collection/add",
 *     "add-form" = "/collection/add/{collection_type}",
 *     "edit-form" = "/collection/{collection}/edit",
 *     "delete-form" = "/collection/{collection}/delete",
 *     "collection" = "/admin/collections",
 *   },
 *   bundle_entity_type = "collection_type",
 *   field_ui_base_route = "entity.collection_type.edit_form"
 * )
 */
class Collection extends EditorialContentEntityBase implements CollectionInterface {

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
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    // Delete the collection items of a deleted collection.
    $items_for_deletion = [];

    foreach ($entities as $entity) {
      $items = $entity->getItems();
      if (empty($items)) {
        continue;
      }

      foreach ($items as $item) {
        $items_for_deletion[$item->id()] = $item;
      }
    }

    $item_storage = \Drupal::service('entity_type.manager')->getStorage('collection_item');
    $item_storage->delete($items_for_deletion);
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
  public function getOwnerIds() {
    $owner_ids = [];
    $owners = $this->get('user_id');

    foreach ($owners as $owner) {
      $owner_ids[] = $owner->target_id;
    }

    return $owner_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    // Check the new status before running parent::save(), where it will be set
    // to false.
    $is_new = $this->isNew();

    // Save the collection and run core postSave hooks (e.g.
    // hook_entity_insert()).
    $return = parent::save();

    // Get the event_dispatcher service and dispatch the event.
    $event_dispatcher = \Drupal::service('event_dispatcher');

    // Is the collection being inserted (e.g. is new)?
    if ($is_new) {
      // Dispatch new collection event.
      $event = new CollectionCreateEvent($this);
      $event_dispatcher->dispatch(CollectionEvents::COLLECTION_ENTITY_CREATE, $event);
    }
    else { // Check whether the url is being changed.
      // Dispatch update collection event.
      $event = new CollectionUpdateEvent($this);
      $event_dispatcher->dispatch(CollectionEvents::COLLECTION_ENTITY_UPDATE, $event);
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function preSaveRevision(EntityStorageInterface $storage, \stdClass $record) {
    parent::preSaveRevision($storage, $record);

    $is_new_revision = $this->isNewRevision();
    if (!$is_new_revision && isset($this->original) && empty($record->revision_log_message)) {
      // If we are updating an existing collection without adding a new
      // revision, we need to make sure $entity->revision_log_message is reset
      // whenever it is empty. Therefore, this code allows us to avoid
      // clobbering an existing log entry with an empty one.
      $record->revision_log_message = $this->original->revision_log_message->value;
    }

    if ($is_new_revision) {
      $record->revision_created = self::getRequestTime();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getItems() {
    $collection_item_ids = \Drupal::entityQuery('collection_item')
      ->condition('collection', $this->id())
      ->sort('changed', 'DESC')
      ->execute();
    $items = $this->entityTypeManager()->getStorage('collection_item')->loadMultiple($collection_item_ids);
    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function getItem(EntityInterface $entity) {
    foreach ($this->getItems() as $collection_item) {
      if ($collection_item->item->first()->entity === $entity) {
        return $collection_item;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function findItems(string $type) {
    $collection_item_ids = \Drupal::entityQuery('collection_item')
      ->condition('collection', $this->id())
      ->condition('item__target_type', $type)
      ->execute();
    $items = $this->entityTypeManager()->getStorage('collection_item')->loadMultiple($collection_item_ids);
    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function findItemsByAttribute(string $key, string $value) {
    $collection_item_ids = \Drupal::entityQuery('collection_item')
      ->condition('collection', $this->id())
      ->condition('attributes.key', $key)
      ->condition('attributes.value', $value)
      ->execute();
    $items = $this->entityTypeManager()->getStorage('collection_item')->loadMultiple($collection_item_ids);
    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function addItem(EntityInterface $entity) {
    if ($this->getItem($entity)) {
      return FALSE;
    }

    // @todo: This is a temporary workaround to set the type if it finds a
    // matching item type. This will need to be refactored as a part of
    // #allowed_collection_items.
    $defined_bundles = [];
    foreach (\Drupal::service('entity_type.bundle.info')->getBundleInfo('collection_item') as $bundle_key => $bundle_info) {
      $defined_bundles[] = $bundle_key;
    }
    $type = (in_array($this->bundle(), $defined_bundles)) ? $this->bundle() : 'default';

    $collection_item = $this->entityTypeManager()->getStorage('collection_item')->create([
      'collection' => $this->id(),
      'type' => $type,
      'item' => $entity
    ]);

    $collection_item->save();
    return $collection_item;
  }

  /**
   * {@inheritdoc}
   */
  public function removeItem(EntityInterface $entity) {
    if ($existing_collection_item = $this->getItem($entity)) {
      $existing_collection_item->delete();
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Owners'))
      ->setDescription(t('The users that own this Collection.'))
      ->setRevisionable(TRUE)
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the Collection entity.'))
      ->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['path'] = BaseFieldDefinition::create('path')
      ->setLabel(t('URL alias'))
      ->setDescription(t('The collection URL alias.'))
      ->setTranslatable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'path',
        'weight' => 30,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setComputed(TRUE);

    $fields['status']
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
        'weight' => 100,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'))
      ->setRevisionable(TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'))
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
