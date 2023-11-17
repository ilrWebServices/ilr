<?php

namespace Drupal\collection_request\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\collection_request\CollectionRequestListBuilder;
use Drupal\Core\Cache\Cache;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'CollectionRequestBlock' block.
 *
 * @Block(
 *   id = "collections_request_block",
 *   admin_label = @Translation("Collection request block"),
 * )
 */
class CollectionRequestBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\Core\Entity\EntityManagerInterface definition.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * The current route match service.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->account = $container->get('current_user');
    $instance->routeMatch = $container->get('current_route_match');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    if (!$user = $this->routeMatch->getParameter('user')) {
      return [];
    }
    $pending_user_collection_items = [];
    $build = [];
    $collection_item_storage = $this->entityTypeManager->getStorage('collection_item');
    $query = $collection_item_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('attributes.key', 'collection-request-uid')
      ->condition('attributes.value', NULL, 'IS NOT NULL');
    $result = $query->execute();

    foreach ($collection_item_storage->loadMultiple($result) as $collection_item) {
      if ($collection_item->access('update', $user) || $collection_item->getOwnerId() === $user->id()) {
        $pending_user_collection_items[$collection_item->id()] = $collection_item;
      }
    }

    if (empty($pending_user_collection_items)) {
      return $build;
    }

    $collection_item_definition = $this->entityTypeManager->getDefinition('collection_item');
    $list_builder = new CollectionRequestListBuilder($pending_user_collection_items, $collection_item_definition);

    $build = [
      '#theme' => 'container__collection_requests',
      '#children' => [
        'list' => $list_builder->render(),
      ],
      '#attributes' => [
        'class' => ['collection-requests']
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    // Cache this list per-user.
    return Cache::mergeContexts(parent::getCacheContexts(), ['user']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    // Invalidate this cached list whenever any collection item is modified, added,
    // or removed.
    return Cache::mergeTags(parent::getCacheTags(), ['collection_item_list']);
  }

}
