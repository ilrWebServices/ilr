<?php

namespace Drupal\ilr\Plugin\ExtraField\Display;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\extra_field\Plugin\ExtraFieldDisplayBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Example Extra field Display.
 *
 * @ExtraFieldDisplay(
 *   id = "previous_next_story",
 *   label = @Translation("Previous and next story"),
 *   bundles = {
 *     "node.story",
 *   },
 *   visible = true
 * )
 */
class PreviousNextStory extends ExtraFieldDisplayBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->collectionItemStorage = $container->get('entity_type.manager')->getStorage('collection_item');
    $instance->pathAliasEntitiesManager = $container->get('path_alias.entities');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function view(ContentEntityInterface $entity) {
    $collection = FALSE;
    $elements = [];

    // Check the path entities to see if this story is part of a
    // publication_issue collection.
    foreach ($this->pathAliasEntitiesManager->getPathAliasEntities() as $path_entity) {
      if ($path_entity instanceof CollectionInterface && $path_entity->bundle() === 'publication_issue') {
        $collection = $path_entity;
        break;
      }
    }

    if (!$collection) {
      return $elements;
    }

    // Get the collection item for this story in the collection.
    $collection_item_ids = $this->collectionItemStorage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'publication_issue')
      ->condition('collection', $collection->id())
      ->condition('item.entity:node.type', 'story')
      ->condition('item.entity:node.nid', $entity->id())
      ->execute();

    if (!$collection_item_ids) {
      return $elements;
    }

    $collection_item = $this->collectionItemStorage->load(reset($collection_item_ids));

    // Get the story items in this collection. $collection->getItems() will sort
    // the them by weight and then by changed date.
    $story_items = array_filter($collection->getItems(), function ($v) {
      return $v->item->entity->bundle() === 'story';
    });

    $story_item_keys = array_keys($story_items);
    $position = array_search($collection_item->id(), $story_item_keys);

    if (isset($story_item_keys[$position - 1])) {
      $prev_story_id = $story_item_keys[$position - 1];
      $elements['prev_publication_issue_story'] = [
        '#type' => 'link',
        '#title' => $this->t('Previous story'),
        '#url' => $story_items[$prev_story_id]->item->entity->toUrl(),
        '#attributes' => [
          'class' => 'cu-button',
        ],
        '#attached' => [
          'library' => [
            'union_organizer/button',
          ],
        ],
      ];
    }

    if (isset($story_item_keys[$position + 1])) {
      $next_story_id = $story_item_keys[$position + 1];
      $elements['next_publication_issue_story'] = [
        '#type' => 'link',
        '#title' => $this->t('Next story'),
        '#url' => $story_items[$next_story_id]->item->entity->toUrl(),
        '#attributes' => [
          'class' => 'cu-button',
        ],
        '#attached' => [
          'library' => [
            'union_organizer/button',
          ],
        ],
      ];
    }

    return $elements;
  }

}
