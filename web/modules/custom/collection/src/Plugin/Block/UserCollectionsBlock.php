<?php

namespace Drupal\collection\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;

/**
 * Provides a 'UserCollectionsBlock' block.
 *
 * @Block(
 *  id = "user_collections_block",
 *  admin_label = @Translation("User collections block"),
 * )
 */
class UserCollectionsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\Core\Entity\EntityManagerInterface definition.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $route_match;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->route_match = $container->get('current_route_match');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    if (!$user = $this->route_match->getParameter('user')) {
      return [];
    }
    $current_user = \Drupal::currentUser();
    $collection_storage = $this->entityTypeManager->getStorage('collection');
    $query = $collection_storage->getQuery();
    $collection_ids = $query->execute();
    $loaded_collections = $collection_storage->loadMultiple($collection_ids);
    $user_collections = [];
    foreach ($loaded_collections as $collection) {
      // Add the item to the list if the user from the current route has update access.
      if ($collection->access('update', $user)) {
        $user_collections[] = $collection;
      }
    }

    if (empty($user_collections)) {
      return [];
    }

    $build['user_collections_block'] = [
      '#theme' => 'item_list',
      '#items' => [],
      '#empty' => $this->t('No collections.'),
      '#cache' => [
        'contexts' => [
          // Cache this list per-user.
          'user',
        ],
        'tags' => [
          // Invalidate this cached list whenever any collection is modified,
          // added, or removed.
          'collection_list'
        ],
      ],
    ];

    foreach ($user_collections as $collection) {
      $build['user_collections_block']['#items'][] = [
        '#access' => $collection->access('view', $current_user),
        [
          '#type' => 'link',
          '#url' => $collection->toUrl(),
          '#title' => $this->t('@collection', [
            '@collection' => $collection->label()
          ]),
        ],
        [
          '#theme' => 'item_list',
          '#access' => $collection->access('update', $current_user),
          '#items' => [
            [
              '#type' => 'link',
              '#url' => Url::fromRoute('collection_item.new.node', [
                'collection' => $collection->id()
              ]),
              '#title' => $this->t('Add content'),
            ],
            [
              '#type' => 'link',
              '#url' => Url::fromRoute('entity.collection_item.collection', [
                'collection' => $collection->id()
              ]),
              '#title' => $this->t('View items'),
            ]
          ]
        ],
      ];
    }

    return $build;
  }

}
