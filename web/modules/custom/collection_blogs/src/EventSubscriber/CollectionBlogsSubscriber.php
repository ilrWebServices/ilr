<?php

namespace Drupal\collection_blogs\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\AliasManagerInterface;
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
   * The alias manager.
   *
   * @var \Drupal\Core\Path\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * Constructs a new CollectionBlogsSubscriber object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AliasManagerInterface $alias_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->aliasManager = $alias_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      CollectionEvents::COLLECTION_ENTITY_CREATE => 'collectionCreate',
      CollectionEvents::COLLECTION_ITEM_FORM_CREATE => 'collectionItemFormCreate',
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

    // Create a vocab for the blog
    $vocab = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->create([
      'langcode' => 'en',
      'status' => TRUE,
      'name' => $collection->label() . ' categories',
      'vid' => 'blog_' . $collection->id() . '_categories',
      'description' => 'Auto-generated vocabulary for ' . $collection->label() . ' blog',
    ]);
    $vocab->save();

    if ($vocab) {
      // Add the vocab to this new collection.
      $collection_item_vocab = $this->entityTypeManager->getStorage('collection_item')->create([
        'type' => 'blog',
        'collection' => $collection->id(),
      ]);

      $collection_item_vocab->item = $vocab;
      $collection_item_vocab->setAttribute('blog_collection_id', $collection->id());
      $collection_item_vocab->save();

      // Create a pattern for the new vocabulary
      $collection_alias = $this->aliasManager->getAliasByPath($collection->toUrl()->toString());
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');

      $pattern = $this->entityTypeManager->getStorage('pathauto_pattern')->create([
        'id' => $vocab->id() . '_terms',
        'label' => $vocab->label() . ' Terms',
        'type' => 'canonical_entities:taxonomy_term',
        'status' => TRUE,
      ]);
      $pattern->setPattern($collection_alias . '/[term:name]');
      $pattern->addSelectionCondition([
        'id' => 'entity_bundle:taxonomy_term',
        'bundles' => [$vocab->id() => $vocab->id()],
        'negate' => FALSE,
        'context_mapping' => ['taxonomy_term' => 'taxonomy_term'],
      ]);
      $pattern->save();
    }
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
