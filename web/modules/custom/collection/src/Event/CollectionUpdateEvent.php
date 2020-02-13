<?php

namespace Drupal\collection\Event;

use Symfony\Component\EventDispatcher\Event;
use Drupal\collection\Entity\CollectionInterface;

/**
 * Event that is fired when a collection is saved.
 */
class CollectionUpdateEvent extends Event {

  /**
   * The collection.
   *
   * @var \Drupal\collection\Entity\CollectionInterface
   */
  public $collection;

  /**
   * Constructs the object.
   *
   * @param \Drupal\collection\Entity\CollectionInterface $collection
   *   The collection being created.
   */
  public function __construct(CollectionInterface $collection) {
    $this->collection = $collection;
  }

}
