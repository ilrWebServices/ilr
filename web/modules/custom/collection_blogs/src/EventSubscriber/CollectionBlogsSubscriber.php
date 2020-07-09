<?php

namespace Drupal\collection_blogs\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\Core\Config\DatabaseStorage;
use Drupal\collection\Event\CollectionEvents;
use Drupal\Core\Config\ConfigEvents;
use Symfony\Component\EventDispatcher\Event;
use Drupal\Core\Config\StorageTransformEvent;

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
   * The database (active) storage.
   *
   * @var \Drupal\Core\Config\DatabaseStorage
   */
  protected $activeStorage;

  /**
   * Constructs a new CollectionBlogsSubscriber object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AliasManagerInterface $alias_manager, DatabaseStorage $database_storage) {
    $this->entityTypeManager = $entity_type_manager;
    $this->aliasManager = $alias_manager;
    $this->activeStorage = $database_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      CollectionEvents::COLLECTION_ENTITY_CREATE => 'collectionCreate',
      ConfigEvents::STORAGE_TRANSFORM_IMPORT => 'onImportTransform',
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
        ]);

        $collection_item_vocab->item = $vocab;
        $collection_item_vocab->setAttribute('blog_taxonomy_' . $vocabulary_type, TRUE);
        $collection_item_vocab->save();

        // Create a pattern for the new vocabulary
        $collection_alias = $this->aliasManager->getAliasByPath($collection->toUrl()->toString());

        $pattern = $this->entityTypeManager->getStorage('pathauto_pattern')->create([
          'id' => $vocab->id() . '_terms',
          'label' => $vocab->label() . ' Terms',
          'type' => 'canonical_entities:taxonomy_term',
          'status' => TRUE,
        ]);
        // This prevents duplicate paths when both the categories and tags
        // vocabularies have the same term. Categories are special in that their
        // terms are also used in the post aliases.
        $subpath = ($vocabulary_type !== 'categories') ? '/' . $vocabulary_type : '';
        $pattern->setPattern($collection_alias . $subpath . '/[term:name]');
        $pattern->addSelectionCondition([
          'id' => 'entity_bundle:taxonomy_term',
          'bundles' => [$vocab->id() => $vocab->id()],
          'negate' => FALSE,
          'context_mapping' => ['taxonomy_term' => 'taxonomy_term'],
        ]);
        $pattern->save();
      }
    }
  }

  /**
   * Ignore blog-related config entities.
   *
   * This prevents deleting configuration during deployment and configuration
   * synchronization.
   *
   * @param \Drupal\Core\Config\StorageTransformEvent $event The config storage
   *   transform event.
   */
  public function onImportTransform(StorageTransformEvent $event) {
    /** @var \Drupal\Core\Config\StorageInterface $sync_storage */
    $sync_storage = $event->getStorage();

    // List the patterns that we don't want to mistakenly remove from the active store.
    $collection_blogs_config_patterns = [
      'taxonomy.vocabulary.blog_', // e.g. taxonomy.vocabulary.blog_2_categories
      'pathauto.pattern.blog_', // e.g. pathauto.pattern.blog_2_categories_terms
    ];

    // Filter active configuration for the ignored items.
    $ignored_config = array_filter($this->activeStorage->listAll(), function($config_name) use ($collection_blogs_config_patterns) {
      foreach ($collection_blogs_config_patterns as $pattern) {
        // @todo Change to regex pattern if any name collisions occur.
        if (strpos($config_name, $pattern) !== FALSE) {
          return TRUE;
        }
      }
      return FALSE;
    });

    // Set the sync_storage to the active store values. This makes them appear
    // to be identical ("There are no changes to import").
    foreach ($ignored_config as $config_name) {
      $sync_storage->write($config_name, $this->activeStorage->read($config_name));
    }
  }

}
