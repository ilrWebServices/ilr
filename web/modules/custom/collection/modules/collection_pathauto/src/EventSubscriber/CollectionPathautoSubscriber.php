<?php

namespace Drupal\collection_pathauto\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\collection\Event\CollectionEvents;
use Symfony\Component\EventDispatcher\Event;
use Drupal\pathauto\PathautoFieldItemList;

/**
 * Class CollectionPathautoSubscriber.
 */
class CollectionPathautoSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      CollectionEvents::COLLECTION_ITEM_FORM_SAVE => 'collectionItemFormSave',
    ];
  }

  /**
   * Process the COLLECTION_ITEM_FORM_SAVE event.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The dispatched event.
   */
  public function collectionItemFormSave(Event $event) {
    $collection_item = $event->collectionItem;
    $collection = $collection_item->collection->first()->entity;
    $collection_item_entity = $collection_item->item->first()->entity;

    // See if this collection item entity uses a pathauto alias.
    if ($collection_item_entity->path instanceof PathautoFieldItemList && $collection_item_entity->path->pathauto) {
      // Re-save the item entity to trigger path alias update.
      $collection_item_entity->save();
    }
  }

}
