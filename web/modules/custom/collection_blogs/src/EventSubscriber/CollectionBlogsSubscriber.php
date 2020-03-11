<?php

namespace Drupal\collection_blogs\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\collection\Event\CollectionEvents;
use Symfony\Component\EventDispatcher\Event;

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
   * Constructs a new CollectionBlogsSubscriber object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      CollectionEvents::COLLECTION_ITEM_FORM_CREATE => 'collectionItemFormCreate',
    ];
  }

  /**
   * Process the COLLECTION_ITEM_FORM_CREATE event.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The dispatched event.
   */
  public function collectionItemFormCreate(Event $event) {
    $collection_item = $event->collectionItem;
    $collection = $collection_item->collection->first()->entity;
    $collection_item_entity = $collection_item->item->first()->entity;
    $collection_type = $this->entityTypeManager->getStorage('collection_type')->load($collection->bundle());
    $is_blog = (bool) $collection_type->getThirdPartySetting('collection_blogs', 'contains_blogs');
    $clean_title = '';

    if (!$is_blog) {
      return;
    }

    // Compare internal path to alias to determine if a manual alias has
    // already been created.
    $entity_path = $collection_item_entity->toUrl()->toString();
    $internal_path = '/' . $collection_item_entity->toUrl()->getInternalPath();

    if (\Drupal::moduleHandler()->moduleExists('pathauto')) {
      $generator = \Drupal::service('pathauto.generator');
      if ($pathauto_alias = $generator->createEntityAlias($collection_item_entity, 'return')) {
        if ($entity_path !== $pathauto_alias) {
          return;
        }
      }

      $alias_cleaner = \Drupal::service('pathauto.alias_cleaner');
      $clean_title = $alias_cleaner->cleanString($collection_item_entity->label());

      // Tell pathauto to skip creating the alias
      $collection_item_entity->path->pathauto = \Drupal\pathauto\PathautoState::SKIP;
    }
    elseif ($entity_path === $internal_path) {
      $clean_title = strtolower(Html::cleanCssIdentifier($collection_item_entity->label()));
    }

    if (!empty($clean_title)) {
      $collection_item_entity->path->alias = $collection->toUrl()->toString() . '/' . $clean_title;
      $collection_item_entity->save();
    }
  }
}
