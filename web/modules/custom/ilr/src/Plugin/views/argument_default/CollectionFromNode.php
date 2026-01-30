<?php

namespace Drupal\ilr\Plugin\views\argument_default;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\collection\CollectionContentManager;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Drupal\node\Plugin\views\argument_default\Node;
use Drupal\views\Attribute\ViewsArgumentDefault;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default argument plugin to extract a collection from the current node.
 */
#[ViewsArgumentDefault(
  id: 'collection_from_node',
  title: new TranslatableMarkup('Collection ID from node in URL'),
)]
class CollectionFromNode extends Node {

  /**
   * The collection content manager.
   *
   * @var \Drupal\collection\CollectionContentManager
   */
  protected $collectionContentManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match, CollectionContentManager $collection_content_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $route_match);
    $this->collectionContentManager = $collection_content_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('collection.content_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getArgument() {
    $node = $this->routeMatch->getParameter('node') ?? $this->routeMatch->getParameter('node_preview');

    if ($node instanceof NodeInterface) {
      foreach ($this->collectionContentManager->getCollectionItemsForEntity($node) as $collection_item) {
        if ($collection_item->isCanonical()) {
          return $collection_item->collection->target_id;
        }
      }
    }
  }

}
