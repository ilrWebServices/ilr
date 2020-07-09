<?php

namespace Drupal\collection\Event;

use Symfony\Component\EventDispatcher\Event;
use Drupal\collection\Entity\CollectionItemInterface;

/**
 * Event that is fired when a collection item is saved via a form.
 */
class CollectionItemFormSaveEvent extends Event {

  /**
   * The collection item being created.
   *
   * @var \Drupal\collection\Entity\CollectionItemInterface
   */
  public $collectionItem;

  /**
   * The return status of the collection item (e.g. SAVED_NEW or SAVED_UPDATED).
   *
   * @see common.inc
   */
  public $returnStatus;

  /**
   * Constructs the object.
   *
   * @param \Drupal\collection\Entity\CollectionItemInterface $collection_item
   *   The collection item being inserted or updated.
   *
   * @param string $operation
   */
  public function __construct(CollectionItemInterface $collection_item, $return_status) {
    $this->collectionItem = $collection_item;
    $this->returnStatus = $return_status;
  }

}
