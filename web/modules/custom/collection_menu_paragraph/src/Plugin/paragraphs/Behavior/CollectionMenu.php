<?php

namespace Drupal\collection_menu_paragraph\Plugin\paragraphs\Behavior;

use Drupal\paragraphs\ParagraphsBehaviorBase;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuActiveTrailInterface;
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
   * The frame position options
   */
  protected $navigation_levels = [
    'children' => 'Children',
    'siblings' => 'Siblings',
  ];

  /**
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuTree;

  /**
   * @var \Drupal\Core\Menu\MenuActiveTrailInterface
   */
  protected $menuActiveTrail;

  /**
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
      '#options' => $this->navigation_levels,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'navigation_level'),
      '#suffix' => $this->t(' of this page')
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
      return;
    }

    $active_link = $this->menuActiveTrail->getActiveLink($menu_name);

    if (!$active_link) {
      return;
    }

    $parameters = new MenuTreeParameters();
    // $parameters = $this->menuTree->getCurrentRouteMenuTreeParameters($menu_name);

    if ($paragraphs_entity->getBehaviorSetting($this->getPluginId(), 'navigation_level') === 'children') {
      $parameters->setRoot($active_link->getPluginId());
    }
    else {
      $parameters->setRoot($active_link->getParent());
    }

    $parameters->setMaxDepth(1);
    $parameters->excludeRoot(); // This could be a setting (e.g. 'Show parent').

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
   * This behavior is only applicable to paragraphs that are of type 'section_navigation'.
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    return $paragraphs_type->id() === 'section_navigation';
  }

  /**
   * Get the menu name for the first path entity if it is a collection.
   *
   * @return string
   *   The name of the menu for the first path entity or FALSE if no menu was found.
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

    return $menu ? $menu->id() : FALSE;
  }
}
