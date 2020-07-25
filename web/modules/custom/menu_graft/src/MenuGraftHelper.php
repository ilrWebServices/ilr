<?php

namespace Drupal\menu_graft;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\menu_link_content\MenuLinkContentInterface;

/**
 * Class MenuGraftHelper.
 */
class MenuGraftHelper {

  /**
   * Menu storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $menuStorage;

  /**
   * Menu Link Manager.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $menuLinkManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type menuLinkManager.
   * @param \Drupal\Core\Menu\MenuLinkManagerInterface $menuLinkManager
   *   The menu link menuLinkManager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MenuLinkManagerInterface $menu_link_manager) {
    $this->menuStorage = $entity_type_manager->getStorage('menu');
    $this->menuLinkManager = $menu_link_manager;
  }

  /**
   * Create a 'mirrored' menu entries associated with a new menu_link_content
   * entity.
   *
   * @param \Drupal\menu_link_content\MenuLinkContentInterface
   *   $menu_link_content A menu_link_content entity
   */
  public function addMenuGraftLinkItem(MenuLinkContentInterface $menu_link_content) {
    $root_menu_info = $this->getGraftRootMenuInfo($menu_link_content->getMenuName());

    if (!$root_menu_info) {
      return;
    }

    $plugin_def = $menu_link_content->getPluginDefinition();

    // Create a blank 'mirror' graft link plugin definition.
    $menu_graft_plugin_def = [
      // TODO See if there is a way to get class, deriver, and provider automatically.
      "class" => "Drupal\menu_graft\Plugin\Menu\MenuGraftMenuLink",
      "deriver" => "Drupal\menu_graft\Plugin\Derivative\MenuGraftMenuLink", // Unnecessary?
      "provider" => "menu_graft",
      "menu" => $root_menu_info['menu_name'],
      "id" => "menu_graft.menu_link:" . $menu_link_content->getPluginId(),
    ];

    $menu_graft_plugin_def = $this->mapMenuGraftLinkDefinition($menu_link_content->getPluginDefinition(), $menu_graft_plugin_def, $root_menu_info);
    $this->menuLinkManager->addDefinition($menu_graft_plugin_def['id'], $menu_graft_plugin_def);
  }

  /**
   *
   */
  public function removeMenuGraftLinkItem(MenuLinkContentInterface $menu_link_content) {
    $menu_graft_plugin_id = 'menu_graft.menu_link:' . $menu_link_content->getPluginId();
    $this->menuLinkManager->removeDefinition($menu_graft_plugin_id);
  }

  /**
   *
   */
  public function updateMenuGraftLinkItem(MenuLinkContentInterface $menu_link_content) {
    $root_menu_info = $this->getGraftRootMenuInfo($menu_link_content->getMenuName());

    if (!$root_menu_info) {
      return;
    }

    // Get the 'mirror' graft link plugin definition.
    $menu_graft_plugin_id = 'menu_graft.menu_link:' . $menu_link_content->getPluginId();
    $menu_graft_plugin_def = $this->menuLinkManager->getDefinition($menu_graft_plugin_id);

    // Update it.
    $menu_graft_plugin_def = $this->mapMenuGraftLinkDefinition($menu_link_content->getPluginDefinition(), $menu_graft_plugin_def, $root_menu_info);

    // Set a special key that will be detected in
    // \Drupal\menu_graft\Plugin\Menu\MenuGraftMenuLink::updateLink() so that
    // normal edit restrictions are ignored.
    $menu_graft_plugin_def['menu_graft_cascade_update'] = 1;
    $this->menuLinkManager->updateDefinition($menu_graft_plugin_def['id'], $menu_graft_plugin_def);
  }

  /**
   *
   */
  protected function getGraftRootMenuInfo($menu_name) {
    $menu = $this->menuStorage->load($menu_name);
    $menu_link_parent = $menu->getThirdPartySetting('menu_graft', 'menu_graft_link');

    if ($menu_link_parent) {
      $menu_link_parent_parts = explode(':', $menu_link_parent, 2);
      return [
        'menu_name' => $menu_link_parent_parts[0],
        'menu_link_id' => $menu_link_parent_parts[1],
      ];
    }
    else {
      return FALSE;
    }
  }

  /**
   *
   */
  protected function mapMenuGraftLinkDefinition(array $root_plugin_def, array $graft_plugin_def, array $root_menu_info) {
    // Update the graft plugin definition with values from the root link plugin
    // definition.
    $mapped_keys = ['title', 'description', 'route_name', 'route_parameters', 'weight', 'enabled', 'expanded'];
    foreach ($mapped_keys as $mapped_key) {
      $graft_plugin_def[$mapped_key] = $root_plugin_def[$mapped_key];
    }

    // The parent key is special. If the graft menu link parent has a value,
    // should have its parent set to the 'mirror' copy of the parent link.
    // Otherwise, it should be at the root of the menu graft point.
    if ($root_plugin_def['parent']) {
      $graft_plugin_def['parent'] = 'menu_graft.menu_link:' . $root_plugin_def['parent'];
    }
    else {
      $graft_plugin_def['parent'] = $root_menu_info['menu_link_id'];
    }

    return $graft_plugin_def;
  }

}
