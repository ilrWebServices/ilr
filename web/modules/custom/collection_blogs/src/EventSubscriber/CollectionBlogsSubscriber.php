<?php

namespace Drupal\collection_blogs\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\collection\Event\CollectionEvents;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class CollectionSubsitesSubscriber.
 */
class CollectionBlogsSubscriber implements EventSubscriberInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * Constructs a new CollectionBlogsSubscriber object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AliasManagerInterface $alias_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->aliasManager = $alias_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      CollectionEvents::COLLECTION_ENTITY_CREATE => 'collectionCreate',
    ];
  }

  /**
   * Process the COLLECTION_CREATE event.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The dispatched event.
   */
  public function collectionCreate(Event $event) {
    $collection = $event->collection;
    $collection_type = $this->entityTypeManager->getStorage('collection_type')->load($collection->bundle());
    $is_blog = (bool) $collection_type->getThirdPartySetting('collection_blogs', 'contains_blogs');

    if ($is_blog === FALSE) {
      return;
    }

    foreach (['categories', 'tags'] as $vocabulary_type) {
      $vocab = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->create([
        'langcode' => 'en',
        'status' => TRUE,
        'name' => $collection->label() . ' ' . $vocabulary_type,
        'vid' => 'blog_' . $collection->id() . '_' . $vocabulary_type,
        'description' => 'Auto-generated vocabulary for ' . $collection->label() . ' blog',
      ]);
      $vocab->save();

      if ($vocab) {
        // Add the vocab to this new collection.
        $collection_item_vocab = $this->entityTypeManager->getStorage('collection_item')->create([
          'type' => 'default',
          'collection' => $collection->id(),
          'canonical' => TRUE,
          'weight' => 10,
        ]);

        $collection_item_vocab->item = $vocab;
        $collection_item_vocab->setAttribute('blog_taxonomy_' . $vocabulary_type, $vocab->id());
        $collection_item_vocab->save();
      }
    }
  }

}
