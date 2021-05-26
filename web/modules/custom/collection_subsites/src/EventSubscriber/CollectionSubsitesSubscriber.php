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
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Subscriber for events related to subsite collections.
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
      CollectionEvents::COLLECTION_ENTITY_UPDATE => 'collectionUpdate',
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
        '%menu_name' => $menu->label(),
      ]));

      // Add the menu to this new collection.
      $collection_item_menu = $collection_item_storage->create([
      // @todo Consider a dedicated type.
        'type' => 'default',
        'collection' => $collection->id(),
      ]);
      $collection_item_menu->item = $menu;
      $collection_item_menu->setAttribute('subsite_collection_id', $collection->id());
      $collection_item_menu->save();
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
    // e.g. '/scheinman-institute*',.
      'pages' => $collection->toUrl()->toString() . '*',
      'negate' => FALSE,
      'context_mapping' => [],
    ]);

    $bvg->save();

    if ($bvg) {
      $this->messenger->addMessage($this->t('Created new %bvg_name subsite block visibility group.', [
        '%bvg_name' => $bvg->label(),
      ]));

      // Add the bvg to this new collection.
      $collection_item_bvg = $collection_item_storage->create([
      // @todo Consider a dedicated type.
        'type' => 'default',
        'collection' => $collection->id(),
      ]);
      $collection_item_bvg->item = $bvg;
      $collection_item_bvg->setAttribute('subsite_collection_id', $collection->id());
      $collection_item_bvg->save();

      // @todo Add a subsite branding block to the BVG.
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

  /**
   * Process the COLLECTION_ENTITY_CREATE event.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The dispatched event.
   */
  public function collectionUpdate(Event $event) {
    $collection = $event->collection;
    $collection_type = $this->entityTypeManager->getStorage('collection_type')->load($collection->bundle());
    $is_subsite = (bool) $collection_type->getThirdPartySetting('collection_subsites', 'contains_subsites');

    if ($is_subsite === FALSE) {
      return;
    }

    if (($bvg_collection_items = $collection->findItems('block_visibility_group')) === FALSE) {
      return;
    }

    // Check if the subsite path condition needs updating.
    foreach ($bvg_collection_items as $bvg_collection_item) {
      if ($bvg_collection_item->getAttribute('subsite_collection_id') === FALSE) {
        continue;
      }

      $bvg = $bvg_collection_item->item->first()->entity;
      $path_changed = FALSE;

      foreach ($bvg->getConditions() as $condition_id => $condition) {
        if ($condition->getPluginId() !== 'request_path') {
          continue;
        }

        $condition_config = $condition->getConfiguration();

        // Leave negate conditions alone. They're used, for example if a subsite
        // has a blog collection in it as well, such as the Worker Institute.
        // @todo - Update collection module event to include the original value
        // for comparison.
        if ($condition_config['negate'] === TRUE) {
          return;
        }

        $path_changed = $condition_config['pages'] !== $collection->toUrl()->toString() . '*';
        $condition_config['pages'] = $collection->toUrl()->toString() . '*';
        $condition->setConfiguration($condition_config);
      }

      if ($path_changed && $bvg->save()) {
        $this->messenger->addMessage($this->t('Updated the path condition for %bvg_name block visibility group.', [
          '%bvg_name' => $bvg->label(),
        ]));
      }
    }
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
    $collection_type = $this->entityTypeManager->getStorage('collection_type')->load($collection->bundle());
    $is_subsite = (bool) $collection_type->getThirdPartySetting('collection_subsites', 'contains_subsites');

    if (!$is_subsite) {
      return;
    }

    // Check whether there is a menu and add a link if so.
    foreach ($collection->findItems('menu') as $collection_item_menu) {
      $subsite_menu = $collection_item_menu->item->first()->entity;

      if (strpos($subsite_menu->id(), 'subsite-') !== FALSE) {
        $url = Url::fromRoute(
          'entity.menu.add_link_form',
          ['menu' => $subsite_menu->id()]
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
          '%menu' => $subsite_menu->label(),
        ]);

        $this->messenger->addMessage(Link::fromTextAndUrl($text, $url));
      }
    }
  }

}
