<?php

namespace Drupal\collection\Event;

/**
 * Class CollectionEvents
 * @package Drupal\collection\Event
 */
final class CollectionEvents {
  /**
   * Name of the event fired after a new collection entity is saved.
   *
   * @Event
   * @see \Drupal\collection\Event\CollectionEvents
   * @var string
   */
  const COLLECTION_ENTITY_CREATE = 'collection.entity.create';

  /**
   * Name of the event fired after an existing collection entity is saved.
   *
   * @Event
   * @see \Drupal\collection\Event\CollectionEvents
   * @var string
   */
  const COLLECTION_ENTITY_UPDATE = 'collection.entity.update';

  /**
   * Name of the event fired after a collection item entity is saved via a form.
   *
   * @Event
   * @see \Drupal\collection\Event\CollectionEvents
   * @var string
   */
  const COLLECTION_ITEM_FORM_CREATE = 'collection_item.form.create';
}
