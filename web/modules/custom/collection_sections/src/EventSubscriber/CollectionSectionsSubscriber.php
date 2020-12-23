<?php

namespace Drupal\collection_sections\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\collection\Event\CollectionEvents;
use Symfony\Component\EventDispatcher\Event;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Subscriber for events related to content section collections.
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
   * Constructs a new MenuSectionsSubscriber object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger, TranslationInterface $string_translation) {
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      CollectionEvents::COLLECTION_ENTITY_CREATE => 'collectionCreate',
      CollectionEvents::COLLECTION_ITEM_FORM_SAVE => 'collectionItemFormSave',
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
        '%menu_name' => $menu->label(),
      ]));

      // Add the menu to this new collection.
      $collection_item_menu = $collection_item_storage->create([
      // @todo Consider a dedicated type.
        'type' => 'default',
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

}
