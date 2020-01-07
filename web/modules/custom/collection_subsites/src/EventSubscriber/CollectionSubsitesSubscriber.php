<?php

namespace Drupal\collection_subsites\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\collection\Event\CollectionEvents;
use Symfony\Component\EventDispatcher\Event;
use Drupal\Core\Extension\ThemeHandlerInterface;

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
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * Constructs a new MenuSubsitesSubscriber object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger, TranslationInterface $string_translation, ThemeHandlerInterface $theme_handler) {
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->stringTranslation = $string_translation;
    $this->themeHandler = $theme_handler;
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

      // Add the collection to the new menu.
      $menu_link_content = $this->entityTypeManager->getStorage('menu_link_content')->create([
        'title' => $collection->label() . ' Home',
        'menu_name' => $collection_machine_name,
        'link' => ['uri' => 'entity:collection/' . $collection->id()],
        'weight' => -1,
        'expanded' => TRUE,
        'langcode' => 'en'
      ]);
      $menu_link_content->save();
    }

    // Create a block visibility group for this subsite.
    $bvg_storage = $this->entityTypeManager->getStorage('block_visibility_group');
    $bvg = $bvg_storage->create([
      'label' => $collection->label() . ' subsite',
      'id' => str_replace('-', '_', $collection_machine_name),
      'logic' => 'and',
    ]);

    // Add the subsite collection path to the BVG as a condition.
    $bvg->addCondition([
      'id' => 'request_path',
      'pages' => $collection->path->first()->alias . '*', // e.g. '/scheinman-institute*',
      'negate' => FALSE,
      'context_mapping' => [],
    ]);

    // Add the subsite menu to the BVG as a condition.
    $bvg->addCondition([
      'id' => 'menu_position',
      'menu_parent' => $collection_machine_name . ':',
      'negate' => FALSE,
      'context_mapping' => [],
    ]);

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

      // @todo: Add a subsite branding block to the BVG.

      if ($menu) {
        // Add the new menu block to the header region of the new
        // block visibility group.
        $block_storage = $this->entityTypeManager->getStorage('block');
        $default_theme = $this->themeHandler->getDefault();
        $subsite_menu_block = $block_storage->create([
          'id' => $default_theme . '_menu_' . str_replace('-', '_', $collection_machine_name),
          'plugin' => 'system_menu_block:' . $collection_machine_name,
          'theme' => $default_theme,
          'region' => 'header',
          'settings' => [
            'label' => $collection->label() . ' menu block',
            'label_display' => FALSE,
          ],
          'weight' => 100,
        ]);
        $subsite_menu_block->setVisibilityConfig('condition_group', [
          'id' => 'condition_group',
          'negate' => FALSE,
          'block_visibility_group' => $bvg->id(),
        ]);
        $subsite_menu_block->save();
      }
    }

    // @todo Remove the following if https://www.drupal.org/project/menu_item_extras/issues/3061342 is fixed.
    \Drupal::service('cache.discovery')->deleteAll();
    \Drupal::service('kernel')->rebuildContainer();
  }

}
