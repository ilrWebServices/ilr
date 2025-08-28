<?php

namespace Drupal\ilr\Plugin\ExtraField\Display;

use Drupal\collection\CollectionContentManager;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\extra_field\Plugin\ExtraFieldDisplayBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\path_alias_entities\PathAliasEntities;

/**
 * 'Related posts' pseudo field for posts.
 *
 * @ExtraFieldDisplay(
 *   id = "related_posts",
 *   label = @Translation("Related posts"),
 *   bundles = {
 *     "node.post",
 *   },
 *   visible = true
 * )
 */
class RelatedPosts extends ExtraFieldDisplayBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected CollectionContentManager $collectionContentManager,
    protected PathAliasEntities $pathAliasEntities,
    protected array $collectionItemsForNids = []
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('collection.content_manager'),
      $container->get('path_alias.entities')
    );
  }

  /**
   * {@inheritdoc}
   *
   * $entity is always a node, Aaron.
   */
  public function view(ContentEntityInterface $entity) {
    $elements = [];
    $related_posts = [];

    if ($entity->hasField('field_related_posts')) {
      $related_posts = $entity->field_related_posts->referencedEntities();
    }

    $path_collection_entity = FALSE;

    foreach (array_reverse($this->pathAliasEntities->getPathAliasEntities()) as $path_entity) {
      if ($path_entity instanceof CollectionInterface) {
        $path_collection_entity = $path_entity;
        break;
      }
    }

    if (!$path_collection_entity) {
      return $elements;
    }

    $posts_needed = 3 - count($related_posts);

    if ($posts_needed > 0) {
      $remaining_category_collection_item_id_query = $this->entityTypeManager->getStorage('collection_item')->getQuery()
        ->accessCheck(FALSE)
        ->condition('collection', $path_collection_entity->id())
        ->condition('type', 'blog')
        ->condition('item.entity:node.status', 1)
        ->condition('item.entity:node.nid', $entity->id(), '<>')
        ->condition('item.entity:node.type', $entity->bundle())
        ->range(0, $posts_needed)
        ->sort('sticky', 'DESC')
        ->sort('item.entity:node.field_published_date', 'DESC')
        ->sort('item.entity:node.created', 'DESC');

      // Look for posts in the same category of collection items for this entity.
      foreach ($this->collectionContentManager->getCollectionItemsForEntity($entity) as $collection_item) {
        if (!$collection_item->hasField('field_blog_categories')) {
          continue;
        }

        if ($collection_item->collection->entity->id() === $path_collection_entity->id() && !$collection_item->field_blog_categories->isEmpty()) {
          $remaining_category_collection_item_id_query->condition('field_blog_categories', $collection_item->field_blog_categories->target_id);
        }
      }

      $remaining_category_collection_item_ids = $remaining_category_collection_item_id_query->execute();

      foreach ($this->entityTypeManager->getStorage('collection_item')->loadMultiple($remaining_category_collection_item_ids) as $remaining_category_collection_item) {
        $related_posts[$remaining_category_collection_item->id()] = $remaining_category_collection_item->item->entity;
        $this->collectionItemsForNids[$remaining_category_collection_item->item->entity->id()] = $remaining_category_collection_item;
        $posts_needed--;
      }
    }

    // Still need moar posts?
    if ($posts_needed > 0) {
      // Query the news collection!
      $current_news_collection_item_id_query = $this->entityTypeManager->getStorage('collection_item')->getQuery()
        ->accessCheck(FALSE)
        ->condition('collection', 26)
        ->condition('type', 'blog')
        ->condition('item.entity:node.status', 1)
        ->condition('item.entity:node.nid', $entity->id(), '<>')
        ->condition('item.entity:node.type', $entity->bundle())
        ->range(0, $posts_needed)
        ->sort('sticky', 'DESC')
        ->sort('item.entity:node.field_published_date', 'DESC')
        ->sort('item.entity:node.created', 'DESC');

      $current_news_collection_item_ids = $current_news_collection_item_id_query->execute();

      foreach ($this->entityTypeManager->getStorage('collection_item')->loadMultiple($current_news_collection_item_ids) as $current_news_collection_item) {
        $related_posts[$current_news_collection_item->id()] = $current_news_collection_item->item->entity;
        $this->collectionItemsForNids[$current_news_collection_item->item->entity->id()] = $current_news_collection_item;
      }
    }

    $elements['related_posts'] = [
      '#theme' => 'item_list__related_posts',
      '#title' => $this->t('You may also like:'),
      '#items' => [],
      '#attributes' => ['class' => 'related-posts'],
      '#context' => ['node' => $entity],
      '#cache' => [
        'tags' => ['node_list:post', 'node_list:experience_report'],
        'context' => ['url'],
      ],
    ];

    $view_builder = $this->entityTypeManager->getViewBuilder('node');

    foreach ($related_posts as $cid => $related_post) {
      $render_element = $view_builder->view($related_post, 'teaser');
      $render_element['#collection_item'] = $this->collectionItemsForNids[$related_post->id()] ?? NULL;
      $elements['related_posts']['#items'][] = $render_element;
    }

    return $elements;
  }

}
