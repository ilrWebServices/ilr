<?php

namespace Drupal\collection_sections\EventSubscriber;

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
use Drupal\Core\Link;

/**
 * Class CollectionSectionsSubscriber.
 */
class CollectionSectionsSubscriber implements EventSubscriberInterface {

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
   * Constructs a new MenuSectionsSubscriber object.
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
      CollectionEvents::COLLECTION_ITEM_FORM_SAVE => 'collectionItemFormSave',
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

    if ($collection->bundle() !== 'content_section') {
      return;
    }

    // Set a unique 'machine name' for this collection. It will be used for the
    // machine name of the generated menu and any
    // other config entities.
    $collection_machine_name = 'section-' . $collection->id();

    // Initialize the collection_item storage. We'll use this later to add
    // auto-generated items to the new collection.
    $collection_item_storage = $this->entityTypeManager->getStorage('collection_item');

    // Create a menu for this section.
    $menu = $this->entityTypeManager->getStorage('menu')->create([
      'langcode' => 'en',
      'status' => TRUE,
      'id' => $collection_machine_name,
      'label' => $collection->label() . ' navigation',
      'description' => 'Auto-generated menu for ' . $collection->label() . ' section',
    ]);
    $menu->save();

    if ($menu) {
      $this->messenger->addMessage($this->t('Created new %menu_name section menu.', [
        '%menu_name' => $menu->label()
      ]));

      // Add the menu to this new collection.
      $collection_item_menu = $collection_item_storage->create([
        'type' => 'default', // @todo: Consider a dedicated type.
        'collection' => $collection->id(),
      ]);
      $collection_item_menu->item = $menu;
      $collection_item_menu->setAttribute('section_collection_id', $collection->id());
      $collection_item_menu->save();
    }

    // @todo Remove the following if https://www.drupal.org/project/menu_item_extras/issues/3061342 is fixed.
    \Drupal::service('cache.discovery')->deleteAll();
    \Drupal::service('kernel')->rebuildContainer();
  }

  /**
   * Process the COLLECTION_ITEM_FORM_SAVE event.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The dispatched event.
   */
  public function collectionItemFormSave(Event $event) {
    if ($event->returnStatus !== SAVED_NEW) {
      return;
    }

    $collection_item = $event->collectionItem;
    $collection = $collection_item->collection->first()->entity;
    $collection_item_entity = $collection_item->item->first()->entity;

    if ($collection->bundle() !== 'content_section') {
      return;
    }

    // Check whether there is a menu and add a link if so.
    foreach ($collection->findItems('menu') as $collection_item_menu) {
      $section_menu = $collection_item_menu->item->first()->entity;

      if (strpos($section_menu->id(), 'section-') !== FALSE) {
        $url = Url::fromRoute(
          'entity.menu.add_link_form',
          ['menu' => $section_menu->id()]
        );

        if (strpos(\Drupal::request()->server->get('HTTP_REFERER'), '/node/') > 0) {
          $destination = Url::fromRoute(
            'collection.node.collections',
            ['node' => $collection_item_entity->id()]
          );
        }
        else {
          $destination = Url::fromRoute(
            'entity.collection_item.collection',
            ['collection' => $collection->id()]
          );
        }

        $url->setOption('query', [
          'destination' => $destination->toString(),
          'edit[title][widget][0][value]' => $collection_item_entity->label(),
          'edit[link][widget][0][uri]' => $collection_item_entity->label() . ' (' . $collection_item_entity->id() . ')',
        ]);

        $text = $this->t('Add %entity to %menu.', [
          '%entity' => $collection_item_entity->label(),
          '%menu' => $section_menu->label(),
        ]);

        $this->messenger->addMessage(Link::fromTextAndUrl($text, $url));
      }
    }
  }

  /**
   * Ignore section-related config entities.
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
    $collection_section_config_patterns = [
      'system.menu.section-',
    ];

    // Filter active configuration for the ignored items.
    $ignored_config = array_filter($this->activeStorage->listAll(), function($config_name) use ($collection_section_config_patterns) {
      foreach ($collection_section_config_patterns as $pattern) {
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
