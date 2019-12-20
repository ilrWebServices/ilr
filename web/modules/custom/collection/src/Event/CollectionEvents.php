<?php

namespace Drupal\collection\Event;

/**
 * Class CollectionEvents
 * @package Drupal\collection\Event
 */
final class CollectionEvents {
  /**
   * Name of the event fired after a collection entity is saved.
   *
   * @Event
   * @see \Drupal\collection\Event\CollectionEvents
   * @var string
   */
  const COLLECTION_ENTITY_CREATE = 'collection.entity.create';
}
