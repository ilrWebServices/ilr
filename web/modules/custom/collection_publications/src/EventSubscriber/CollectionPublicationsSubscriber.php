<?php

namespace Drupal\collection_publications\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\collection\Event\CollectionEvents;
use Symfony\Component\EventDispatcher\Event;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\DatabaseStorage;
use Drupal\Core\Config\StorageTransformEvent;
use Drupal\Core\Url;

/**
 * Class CollectionPublicationsSubscriber.
 */
class CollectionPublicationsSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * The database (active) storage.
   *
   * @var \Drupal\Core\Config\DatabaseStorage
   */
  protected $activeStorage;

  /**
   * Constructs a new MenuSubsitesSubscriber object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger, TranslationInterface $string_translation, ThemeHandlerInterface $theme_handler, DatabaseStorage $database_storage) {
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->stringTranslation = $string_translation;
    $this->themeHandler = $theme_handler;
    $this->activeStorage = $database_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      CollectionEvents::COLLECTION_ENTITY_CREATE => 'collectionCreate',
      CollectionEvents::COLLECTION_ENTITY_UPDATE => 'collectionUpdate',
      CollectionEvents::COLLECTION_ITEM_FORM_CREATE => 'collectionItemFormCreate',
      ConfigEvents::STORAGE_TRANSFORM_IMPORT => 'onImportTransform',
    ];
  }

  /**
   * Process the COLLECTION_ENTITY_CREATE event.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The dispatched event.
   */
  public function collectionCreate(Event $event) {
    $collection = $event->collection;
    $collection_type = $this->entityTypeManager->getStorage('collection_type')->load($collection->bundle());

    if ($collection->bundle() !== 'publication_issue') {
      return;
    }

    // Set a unique 'machine name' for this collection.
    $collection_machine_name = 'publication-issue-' . $collection->id();

    // Initialize the collection_item storage. We'll use this later to add
    // auto-generated items to the new collection.
    $collection_item_storage = $this->entityTypeManager->getStorage('collection_item');

    // Create a block visibility group for this subsite.
    $bvg_storage = $this->entityTypeManager->getStorage('block_visibility_group');
    $bvg = $bvg_storage->create([
      'label' => $collection->label() . ' issue',
      'id' => str_replace('-', '_', $collection_machine_name),
      'logic' => 'and',
    ]);

    // Add the subsite collection path to the BVG as a condition.
    $bvg->addCondition([
      'id' => 'request_path',
      'pages' => $collection->toUrl()->toString() . '*', // e.g. '/ilrie*',
      'negate' => FALSE,
      'context_mapping' => [],
    ]);

    $bvg->save();

    if ($bvg) {
      $this->messenger->addMessage($this->t('Created new %bvg_name publication issue block visibility group.', [
        '%bvg_name' => $bvg->label()
      ]));

      // Add the bvg to this new collection.
      $collection_item_bvg = $collection_item_storage->create([
        'type' => 'default',
        'collection' => $collection->id(),
      ]);
      $collection_item_bvg->item = $bvg;
      $collection_item_bvg->setAttribute('publication_issue_bvg', TRUE);
      $collection_item_bvg->save();
    }
  }

  /**
   * Process the COLLECTION_ENTITY_UPDATE event.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The dispatched event.
   */
  public function collectionUpdate(Event $event) {
    $collection = $event->collection;

    if ($collection->bundle() !== 'publication_issue') {
      return;
    }

    if (!$bvg_collection_items = $collection->findItemsByAttribute('publication_issue_bvg', TRUE)) {
      return;
    }

    // Check if the subsite path condition needs updating.
    $bvg_collection_item = reset($bvg_collection_items);
    $bvg = $bvg_collection_item->item->entity;
    $path_changed = FALSE;

    foreach ($bvg->getConditions() as $condition_id => $condition) {
      if ($condition->getPluginId() !== 'request_path') {
        continue;
      }

      $condition_config = $condition->getConfiguration();
      $path_changed = $condition_config['pages'] !== $collection->toUrl()->toString() . '*';
      $condition_config['pages'] = $collection->toUrl()->toString() . '*';
      $condition->setConfiguration($condition_config);
    }

    if ($path_changed && $bvg->save()) {
      $this->messenger->addMessage($this->t('Updated the path condition for %bvg_name block visibility group.', [
        '%bvg_name' => $bvg->label()
      ]));
    }
  }

  /**
   * Ignore publication-related config entities.
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
    $default_theme = $this->themeHandler->getDefault();

    // List the patterns that we don't want to mistakenly remove from the active store.
    $collection_publication_config_patterns = [
      'block_visibility_groups.block_visibility_group.publication_issue_',
    ];

    // Filter active configuration for the ignored items.
    $ignored_config = array_filter($this->activeStorage->listAll(), function($config_name) use ($collection_publication_config_patterns) {
      foreach ($collection_publication_config_patterns as $pattern) {
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