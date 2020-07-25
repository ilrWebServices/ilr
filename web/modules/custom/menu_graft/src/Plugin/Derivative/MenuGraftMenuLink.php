<?php

namespace Drupal\menu_graft\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\menu_link_content\Plugin\Menu\MenuLinkContent;
use Drupal\Core\Menu\MenuLinkTreeElement;

/**
 * Derivative class that provides the menu links for the Products.
 */
class MenuGraftMenuLink extends DeriverBase implements ContainerDeriverInterface {

   /**
   * @var EntityTypeManagerInterface $entityTypeManager.
   */
  protected $entityTypeManager;

  /**
   * Creates a MenuGraftMenuLink instance.
   *
   * @param $base_plugin_id
   * @param EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct($base_plugin_id, EntityTypeManagerInterface $entity_type_manager, MenuLinkTreeInterface $menu_link_tree) {
    $this->entityTypeManager = $entity_type_manager;
    $this->menuLinkTree = $menu_link_tree;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $base_plugin_id,
      $container->get('entity_type.manager'),
      $container->get('menu.link_tree')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $links = [];

    // Get all the 'graft' menus, e.g. menus that are configured to be grafted
    // onto another menu.
    $menus = $this->entityTypeManager->getStorage('menu')->loadMultiple();
    $graft_menus = array_filter($menus, function($menu) {
      return (bool) $menu->getThirdPartySetting('menu_graft', 'menu_graft_link');
    });

    // For each menu, create a menu item to the same route. Map the old link id to the newly generated one.
    foreach ($graft_menus as $graft_menu) {
      // Get the graft attachment menu link.
      $menu_link_parent = $graft_menu->getThirdPartySetting('menu_graft', 'menu_graft_link');
      list($root_menu_name, $root_menu_graft_item) = explode(':', $menu_link_parent, 2);

      $menu_parameters = new MenuTreeParameters();
      $tree = $this->menuLinkTree->load($graft_menu->id(), $menu_parameters);

      foreach ($tree as $element) {
        $this->generateLinks($links, $element, $base_plugin_definition, $root_menu_name, $root_menu_graft_item);
      }
    }

    return $links;
  }

  protected function generateLinks(array &$links, MenuLinkTreeElement $element, $base_plugin_definition, $root_menu_name, $parent) {
    if ($element->link instanceof MenuLinkContent) {
      $links[$element->link->getPluginId()] = [
        'title' => $element->link->getTitle(),
        'route_name' => $element->link->getRouteName(),
        'route_parameters' => $element->link->getRouteParameters(),
        'parent' => $parent,
        'weight' => $element->link->getWeight(),
        'enabled' => $element->link->isEnabled(),
        'menu_name' => $root_menu_name,
      ] + $base_plugin_definition;
    }

    if ($element->subtree) {
      foreach ($element->subtree as $sub_element) {
        $this->generateLinks($links, $sub_element, $base_plugin_definition, $root_menu_name, $base_plugin_definition['id'] . ':' . $element->link->getPluginId());
      }
    }

    return $links;
  }

}
