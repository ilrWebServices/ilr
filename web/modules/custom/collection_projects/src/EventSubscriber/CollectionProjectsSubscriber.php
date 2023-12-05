<?php

namespace Drupal\collection_projects\EventSubscriber;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\collection\Entity\CollectionItemInterface;
use Drupal\collection\Event\CollectionCreateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\collection\Event\CollectionEvents;
use Drupal\collection\Event\CollectionUpdateEvent;
use Drupal\collection_projects\CollectionProjectsManager;

/**
 * Class CollectionProjectsSubscriber.
 */
class CollectionProjectsSubscriber implements EventSubscriberInterface {

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
   * The collection projects manager.
   *
   * @var \Drupal\collection_projects\CollectionProjectsManager
   */
  protected $collectionProjectsManager;

  /**
   * Constructs a new CollectionProjectsSubscriber object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AliasManagerInterface $alias_manager, CollectionProjectsManager $collection_projects_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->aliasManager = $alias_manager;
    $this->collectionProjectsManager = $collection_projects_manager;
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

    if ($this->collectionProjectsManager->collectionCanContainProjects($collection) === FALSE) {
      return;
    }

    $this->createCollectionFocusAreaVocab($collection);
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

    if ($this->collectionProjectsManager->collectionCanContainProjects($collection) === FALSE) {
      return;
    }

    $vocabulary = $this->collectionProjectsManager->getCollectedVocabularyByKey($collection, 'project_taxonomy_focus_areas');

    if ($vocabulary) {
      return;
    }

    $this->createCollectionFocusAreaVocab($collection);
  }

  /**
   * Create a focus areas vocabulary for a given Collection.
   *
   * @param CollectionInterface $collection
   *   A Collection entity.
   *
   * @return CollectionItemInterface|boolean
   *   A Collection Item entity referencing the new vocabulary.
   */
  protected function createCollectionFocusAreaVocab(CollectionInterface $collection): CollectionItemInterface|bool {
    $vocab = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->create([
      'langcode' => 'en',
      'status' => TRUE,
      'name' => $collection->label() . ' project focus areas',
      'vid' => 'project_' . $collection->id() . '_focus_areas',
      'description' => 'Auto-generated vocabulary for ' . $collection->label() . ' project focus areas.',
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
      $collection_item_vocab->setAttribute('project_taxonomy_focus_areas', $vocab->id());
      $collection_item_vocab->save();
      return $collection_item_vocab;
    }

    return FALSE;
  }

}
