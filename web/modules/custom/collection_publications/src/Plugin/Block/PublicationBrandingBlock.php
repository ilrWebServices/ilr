<?php

namespace Drupal\collection_publications\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Provides a 'PublicationBrandingBlock' block.
 *
 * @Block(
 *   id = "publication_branding_block",
 *   admin_label = @Translation("Publication branding"),
 * )
 */
class PublicationBrandingBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * An array of entities in the current path.
   */
  protected $pathAliasEntities;

  /**
   * The first entity in the path alias, if there is one.
   */
  protected $firstPathEntity;

  /**
   * The last entity in the path alias, if there is one.
   */
  protected $lastPathEntity;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->pathAliasEntities = $container->get('path_alias.entities')->getPathAliasEntities();
    $instance->firstPathEntity = reset($instance->pathAliasEntities);
    $instance->lastPathEntity = end($instance->pathAliasEntities);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account) {
    if (empty($this->pathAliasEntities)) {
      return AccessResult::neutral();
    }

    // Only allow access if the first entity in the path alias is a Publication
    // term.
    if ($this->firstPathEntity->getEntityType()->id() === 'taxonomy_term' && $this->firstPathEntity->bundle() === 'publication') {
      return AccessResult::allowed();
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['#cache']['contexts'] = ['url.path'];
    $cache_tags = $this->firstPathEntity->getCacheTags();
    $has_svg_logo = !$this->firstPathEntity->field_inline_svg_logo->isEmpty();

    if ($has_svg_logo) {
      $logo = $this->firstPathEntity->field_inline_svg_logo->view('default');
    }
    else {
      $logo = $this->firstPathEntity->name->view('default');
    }

    $build['publication_logo'] = [
      '#type' => 'link',
      '#url' => $this->firstPathEntity->toUrl(),
      '#title' => $logo,
    ];

    $build['publication_logo']['#attributes']['class'][] = 'publication-logo';

    if ($this->lastPathEntity->getEntityType()->id() === 'collection' && $this->lastPathEntity->bundle() === 'publication_issue') {
      $cache_tags = array_merge($cache_tags, $this->lastPathEntity->getCacheTags());

      $build['publication_issue_title'] = [
        '#markup' => '<div class="publication-logo--issue">' . $this->lastPathEntity->label() . '</div>',
        '#weight' => 10,
      ];
    }
    elseif ($this->lastPathEntity->getEntityType()->id() === 'node' && $this->lastPathEntity->bundle() === 'story') {
      $build['publication_logo']['#attributes']['class'][] = 'publication-logo--small';

      if (!$this->firstPathEntity->field_subtitle->isEmpty()) {
        $build['publication_subtitle'] = [
          '#markup' => '<div class="publication-logo--subtitle">' . $this->firstPathEntity->field_subtitle->value . '</div>',
          '#weight' => 10,
        ];
      }
    }

    $build['#cache']['tags'] = $cache_tags;
    return $build;
  }

}
