<?php

namespace Drupal\ilr\Plugin\Block;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\Core\Block\Attribute\Block;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\path_alias_entities\PathAliasEntities;
use Drupal\twig_tweak\View\MenuViewBuilder;

/**
 * Provides a copyright/disclosure-menu block.
 *
 * (The menu is added in the template.)
 */
#[Block(
  id: "ilr_copyright_disclosure",
  admin_label: new TranslatableMarkup("Copyright/Disclosure menu"),
  category: new TranslatableMarkup("ILR")
)]
class ILRCopyrightBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Creates an instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected PathAliasEntities $pathAliasEntities,
    protected MenuViewBuilder $menuViewBuilder
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('path_alias.entities'),
      $container->get('twig_tweak.menu_view_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['label_display' => FALSE];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    $build['copyright'] = [
      '#theme' => 'item_list__copyright_crumbs',
      '#items' => [
        [
          '#markup' => '© ' . date('Y') . ' ' . Link::fromTextAndUrl($this->t('Cornell University'), Url::fromUri('https://www.cornell.edu'))->toString(),
        ],
        [
          '#type' => 'link',
          '#title' => $this->t('ILR School'),
          '#url' => Url::fromRoute('<front>'),
        ],
      ],
    ];

    foreach (array_reverse($this->pathAliasEntities->getPathAliasEntities()) as $path_entity) {
      if ($path_entity instanceof CollectionInterface) {
        $collection_type = $path_entity->type->entity;

        if ((bool) $collection_type->getThirdPartySetting('collection_subsites', 'contains_subsites')) {
          $collection = $path_entity;
          $build['copyright']['#items'][] = [
            '#type' => 'link',
            '#title' => $collection->label(),
            '#url' => $collection->toUrl(),
          ];
        }
      }
    }

    $build['disclosures'] = $this->menuViewBuilder->build('disclosures');

    return $build;
  }

  public function getCacheContexts() {
    return ['route.subsite_collection'];
  }

}
