<?php

namespace Drupal\collection_resource_library\EventSubscriber;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\collection\Entity\CollectionItemInterface;
use Drupal\collection\Event\CollectionCreateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\collection\Event\CollectionEvents;
use Drupal\collection\Event\CollectionUpdateEvent;
use Drupal\collection_resource_library\CollectionResourceLibraryManager;

/**
 * Class CollectionResourceLibrarySubscriber.
 */
class CollectionResourceLibrarySubscriber implements EventSubscriberInterface {

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
   * The collection resource library manager.
   *
   * @var \Drupal\collection_resource_library\CollectionResourceLibraryManager
   */
  protected $collectionResourceLibraryManager;

  /**
   * Constructs a new CollectionResourceLibrarySubscriber object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AliasManagerInterface $alias_manager, CollectionResourceLibraryManager $collection_resource_library_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->aliasManager = $alias_manager;
    $this->collectionResourceLibraryManager = $collection_resource_library_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      CollectionEvents::COLLECTION_ENTITY_CREATE => 'collectionCreate',
      CollectionEvents::COLLECTION_ENTITY_UPDATE => 'collectionUpdate',
    ];
  }

  /**
   * Process the COLLECTION_CREATE event.
   *
   * @param \Drupal\collection\Event\CollectionUpdateEvent $event
   *   The dispatched event.
   */
  public function collectionCreate(CollectionCreateEvent $event): void {
    /** @var \Drupal\collection\Entity\CollectionInterface $collection */
    $collection = $event->collection;

    if ($this->collectionResourceLibraryManager->collectionCanContainResourceItems($collection) === FALSE) {
      return;
    }

    $this->createCollectionResearchTopicsVocab($collection);
  }

  /**
   * Process the COLLECTION_UPDATE event.
   *
   * @param \Drupal\collection\Event\CollectionUpdateEvent $event
   *   The dispatched event.
   */
  public function collectionUpdate(CollectionUpdateEvent $event): void {
    /** @var \Drupal\collection\Entity\CollectionInterface $collection */
    $collection = $event->collection;

    if ($this->collectionResourceLibraryManager->collectionCanContainResourceItems($collection) === FALSE) {
      return;
    }

    $vocabulary = $this->collectionResourceLibraryManager->getCollectedVocabularyByKey($collection, 'research_lib_topics');

    if ($vocabulary) {
      return;
    }

    $this->createCollectionResearchTopicsVocab($collection);
  }

  /**
   * Create a topics vocabulary for a given Collection.
   *
   * @param CollectionInterface $collection
   *   A Collection entity.
   *
   * @return CollectionItemInterface|boolean
   *   A Collection Item entity referencing the new vocabulary.
   */
  protected function createCollectionResearchTopicsVocab(CollectionInterface $collection): CollectionItemInterface|bool {
    $vocab = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->create([
      'langcode' => 'en',
      'status' => TRUE,
      'name' => $collection->label() . ' research topics areas',
      'vid' => 'research_lib_' . $collection->id() . '_topics',
      'description' => 'Auto-generated vocabulary for ' . $collection->label() . ' research topics.',
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
      $collection_item_vocab->setAttribute('research_lib_topics', $vocab->id());
      $collection_item_vocab->save();
      return $collection_item_vocab;
    }

    return FALSE;
  }

}
