<?php

namespace Drupal\ilr\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\path_alias_entities\PathAliasEntities;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;

/**
 * Provides a 'CollectionMenuBlock' block.
 *
 * @todo Remove this plugin or move the core functionality to a service that can
 * be shared with
 * \Drupal\collection_menu_paragraph\Plugin\paragraphs\Behavior\CollectionMenu
 *
 * @see \Drupal\system\Plugin\Block\SystemMenuBlock for useful code.
 *
 * @Block(
 *   id = "collection_menu_block",
 *   admin_label = @Translation("Collection Menu"),
 * )
 */
class CollectionMenuBlock extends BlockBase implements ContainerFactoryPluginInterface {


  /**
   * The menu link tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuTree;

  /**
   * The active menu trail service.
   *
   * @var \Drupal\Core\Menu\MenuActiveTrailInterface
   */
  protected $menuActiveTrail;

  /**
   * @var array
   */
  protected $pathEntities;

  /**
   * Constructs a new SystemMenuBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_tree
   *   The menu tree service.
   * @param \Drupal\Core\Menu\MenuActiveTrailInterface $menu_active_trail
   *   The active menu trail service.
   * @param array $path_entities
   *   An array of path alias entities.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MenuLinkTreeInterface $menu_tree, MenuActiveTrailInterface $menu_active_trail, array $path_entities) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->menuTree = $menu_tree;
    $this->menuActiveTrail = $menu_active_trail;
    $this->pathEntities = $path_entities;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('menu.link_tree'),
      $container->get('menu.active_trail'),
      $container->get('path_alias.entities')->getPathAliasEntities()
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    if (empty($this->pathEntities)) {
      return AccessResult::forbidden();
    }

    if (!$this->getCollectionMenuName()) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }

  /**
   * {@inheritdoc}
   *
   * TODO Find (or create) a cache context that only considers the first part of
   * the url.path (e.g. only `foo` from `foo/bar/baz`). See
   * PathParentCacheContext
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['url.path']);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $menu_name = $this->getCollectionMenuName();
    $parameters = new MenuTreeParameters();
    $active_trail = $this->menuActiveTrail->getActiveTrailIds($menu_name);
    $parameters->setActiveTrail($active_trail);
    $tree = $this->menuTree->load($menu_name, $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuTree->transform($tree, $manipulators);
    $build = $this->menuTree->build($tree);

    // If there is only one item in the active trail, none of the items are active.
    // @see Drupal\Core\Menu\MenuActiveTrail::doGetActiveTrailIds().
    if (count($active_trail) === 1) {
      $build['#attributes']['class'][] = 'collection-menu-block--no-active-trail';
    }
    return $build;
  }

  /**
   * Get the menu name for the first path entity if it is a collection.
   *
   * @return string
   *   The name of the menu for the first path entity.
   */
  protected function getCollectionMenuName() {
    if (!(reset($this->pathEntities) instanceof CollectionInterface)) {
      return FALSE;
    }

    $collection = reset($this->pathEntities);
    $menu = FALSE;

    if ($menu_collection_items = $collection->findItems('menu')) {
      foreach ($menu_collection_items as $menu_collection_item) {
        if (empty($menu_collection_item->attributes)) {
          continue;
        }

        $menu = $menu_collection_item->item->entity;
      }
    }

    if (!$menu) {
      return FALSE;
    }

    return $menu->id();
  }

}
