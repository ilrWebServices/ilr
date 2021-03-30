<?php

namespace Drupal\ilr\Plugin\ExtraField\Display;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\extra_field\Plugin\ExtraFieldDisplayBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\collection\CollectionContentManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Canonical home field Display.
 *
 * @ExtraFieldDisplay(
 *   id = "canonical_home",
 *   label = @Translation("Canonical home"),
 *   bundles = {
 *     "collection_item.blog",
 *   },
 *   visible = true
 * )
 */
class CanonicalHome extends ExtraFieldDisplayBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The collection content manager service.
   *
   * @var \Drupal\collection\CollectionContentManager
   */
  protected $collectionContentManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->collectionContentManager = $container->get('collection.content_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function view(ContentEntityInterface $collection_item) {
    $build = [];

    // If the collection item is canonical, nothing to do.
    if ($collection_item->get('canonical')->value === '1') {
      return $build;
    }

    if ($canonical_collection_item = $this->getCanonicalCollectionItem($collection_item->item->entity)) {
      $build['canonical_link'] = [
        '#url' => $canonical_collection_item->item->entity->toUrl(),
        '#title' => $this->t('Originally published in @collection.', ['@collection' => $canonical_collection_item->collection->entity->label()]),
        '#type' => 'link',
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ];
    }

    return $build;
  }

  /**
   * Get the canonical collection item for an entity.
   */
  protected function getCanonicalCollectionItem(ContentEntityInterface $collected_item) {
    foreach ($this->collectionContentManager->getCollectionItemsForEntity($collected_item) as $collection_item) {
      if ($collection_item->isCanonical()) {
        return $collection_item;
      }
    }

    return NULL;
  }

}
