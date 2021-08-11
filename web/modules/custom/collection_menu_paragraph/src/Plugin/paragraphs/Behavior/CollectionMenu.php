<?php

namespace Drupal\collection_menu_paragraph\Plugin\paragraphs\Behavior;

use Drupal\paragraphs\ParagraphsBehaviorBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\collection\Entity\CollectionInterface;

/**
 * Provides a Union section settings behavior.
 *
 * @ParagraphsBehavior(
 *   id = "collection_menu",
 *   label = @Translation("Section navigation"),
 *   description = @Translation("Display the menu for the current collection."),
 *   weight = 1
 * )
 */
class CollectionMenu extends ParagraphsBehaviorBase {

  /**
   * The frame position options.
   *
   * @var array
   */
  protected $navigationLevels = [
    'children' => 'Children of this content',
    'siblings' => 'Siblings of this content',
    'root' => 'Top level of this menu',
  ];

  /**
   * The menu tree.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuTree;

  /**
   * The menu active trail.
   *
   * @var \Drupal\Core\Menu\MenuActiveTrailInterface
   */
  protected $menuActiveTrail;

  /**
   * The entities represented by the path parts.
   *
   * @var array
   */
  protected $pathEntities;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_field.manager'));
    $instance->menuTree = $container->get('menu.link_tree');
    $instance->menuActiveTrail = $container->get('menu.active_trail');
    $instance->pathEntities = $container->get('path_alias.entities')->getPathAliasEntities();
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'navigation_level' => 'children',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $form['navigation_level'] = [
      '#type' => 'select',
      '#title' => $this->t('Navigation to display'),
      '#options' => $this->navigationLevels,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'navigation_level'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraphs_entity, EntityViewDisplayInterface $display, $view_mode) {
    $menu_name = $this->getCollectionMenuName();

    if (!$menu_name) {
      $build['#printed'] = TRUE;
      return;
    }

    $level = $paragraphs_entity->getBehaviorSetting($this->getPluginId(), 'navigation_level');
    $active_link = $this->menuActiveTrail->getActiveLink($menu_name);

    if ($level === 'root') {
      $root = '';
    }
    elseif ($active_link) {
      $root = ($level === 'children') ? $active_link->getPluginId() : $active_link->getParent();
    }
    else {
      $build['#printed'] = TRUE;
      return;
    }

    $parameters = new MenuTreeParameters();
    $parameters->setRoot($root);
    $parameters->setMaxDepth(1);
    // This could be a setting (e.g. 'Show parent').
    $parameters->excludeRoot();

    $tree = $this->menuTree->load($menu_name, $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuTree->transform($tree, $manipulators);
    $build['collection_menu'] = $this->menuTree->build($tree);
    $build['#cache']['contexts'][] = 'url.path';
  }

  /**
   * {@inheritdoc}
   *
   * This behavior is only applicable to paragraphs that are of type
   * 'section_navigation'.
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    return $paragraphs_type->id() === 'section_navigation';
  }

  /**
   * Get the menu name for the first path entity if it is a collection.
   *
   * @return string
   *   The name of the menu for the active collection.
   *   or FALSE if no menu was found.
   */
  protected function getCollectionMenuName() {
    foreach ($this->pathEntities as $entity) {
      if ($entity instanceof CollectionInterface) {
        $collection = $entity;
      }
    }

    if (empty($collection)) {
      return FALSE;
    }

    $menu = FALSE;

    if ($menu_collection_items = $collection->findItems('menu')) {
      foreach ($menu_collection_items as $menu_collection_item) {
        if (empty($menu_collection_item->attributes)) {
          continue;
        }

        $menu = $menu_collection_item->item->entity;
      }
    }

    return $menu ? $menu->id() : FALSE;
  }

}
