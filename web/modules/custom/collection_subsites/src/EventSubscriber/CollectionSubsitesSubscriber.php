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

    // Set a unique 'machine name' for this collection. It will be used for the
    // machine names of the generated menu, block visibility group, and any
    // other config entities.
    $collection_machine_name = 'subsite-' . $collection->id();

    // Initialize the collection_item storage. We'll use this later to add
    // auto-generated items to the new collection.
    $collection_item_storage = $this->entityTypeManager->getStorage('collection_item');

    // Create a menu for this subsite.
    $menu = $this->entityTypeManager->getStorage('menu')->create([
      'langcode' => 'en',
      'status' => TRUE,
      'id' => $collection_machine_name,
      'label' => $collection->label() . ' subsite main navigation',
      'description' => 'Auto-generated menu for ' . $collection->label() . ' subsite',
    ]);
    $menu->save();

    if ($menu) {
      $this->messenger->addMessage($this->t('Created new %menu_name subsite menu.', [
        '%menu_name' => $menu->label()
      ]));

      // Add the menu to this new collection.
      $collection_item_menu = $collection_item_storage->create([
        'type' => 'default', // @todo: Consider a dedicated type.
        'collection' => $collection->id(),
      ]);
      $collection_item_menu->item = $menu;
      $collection_item_menu->save();
    }

    // Create a block visibility group for this subsite.
    $bvg_storage = $this->entityTypeManager->getStorage('block_visibility_group');
    $bvg = $bvg_storage->create([
      'label' => $collection->label() . ' subsite',
      'id' => $collection_machine_name,
      'logic' => 'or',
    ]);

    // Add the subsite collection path to the BVG as a condition.
    $bvg->addCondition([
      'id' => 'request_path',
      'pages' => $collection->path->first()->alias . '*', // e.g. '/scheinman-institute*',
      'negate' => FALSE,
      'context_mapping' => [],
    ]);

    // @todo: Add the menu to the BVG as a condition.

    $bvg->save();

    if ($bvg) {
      $this->messenger->addMessage($this->t('Created new %bvg_name subsite block visibility group.', [
        '%bvg_name' => $bvg->label()
      ]));

      // Add the bvg to this new collection.
      $collection_item_bvg = $collection_item_storage->create([
        'type' => 'default', // @todo: Consider a dedicated type.
        'collection' => $collection->id(),
      ]);
      $collection_item_bvg->item = $bvg;
      $collection_item_bvg->save();
    }
  }

}
