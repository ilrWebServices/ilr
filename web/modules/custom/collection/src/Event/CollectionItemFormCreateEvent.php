<?php

namespace Drupal\collection\Event;

use Symfony\Component\EventDispatcher\Event;
use Drupal\collection\Entity\CollectionItemInterface;

/**
 * Event that is fired when a collection item is created via a form.
 */
class CollectionItemFormCreateEvent extends Event {

  /**
   * The collection item being created.
   *
   * @var \Drupal\collection\Entity\CollectionItemInterface
   */
  public $collectionItem;

  /**
   * Constructs the object.
   *
   * @param \Drupal\collection\Entity\CollectionItemInterface $collection_item
   *   The collection item being created.
   */
  public function __construct(CollectionItemInterface $collection_item) {
    $this->collectionItem = $collection_item;
  }

}
