<?php

namespace Drupal\collection_subsites\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\collection\Event\CollectionEvents;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class CollectionSubsitesSubscriber.
 */
class CollectionSubsitesSubscriber implements EventSubscriberInterface {

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
   * Constructs a new MenuSubsitesSubscriber object.
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
    $is_subsite = (bool) $collection_type->getThirdPartySetting('collection_subsites', 'contains_subsites');

    if ($is_subsite === FALSE) {
      return;
    }

    // Create a menu for this subsite.
    $menu = $this->entityTypeManager->getStorage('menu')->create([
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'subsite-' . $collection->id(),
      'label' => $collection->label() . ' main navigation',
      'description' => 'Auto-generated menu for ' . $collection->label() . ' subsite',
    ]);
    $menu->save();

    if ($menu) {
      $this->messenger->addMessage($this->t('Created new %menu_name subsite menu.', [
        '%menu_name' => $menu->label()
      ]));

      // Add the menu to this new collection.
      $collection_item_storage = $this->entityTypeManager->getStorage('collection_item');
      $collection_item = $collection_item_storage->create([
        'type' => 'default',
        'user_id' => '0',
        'collection' => $collection->id(),
      ]);
      $collection_item->item = $menu;
      $collection_item->save();
    }
  }

}
