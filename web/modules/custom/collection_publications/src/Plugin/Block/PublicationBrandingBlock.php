<?php

namespace Drupal\collection_publications\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\Core\Cache\Cache;


/**
 * Provides a 'PublicationBrandingBlock' block.
 *
 * @Block(
 *   id = "publication_branding_block",
 *   admin_label = @Translation("Publication branding"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", required = FALSE),
 *     "collection" = @ContextDefinition("entity:collection", required = FALSE)
 *   }
 * )
 */
class PublicationBrandingBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\path_alias_entities\pathAliasEntitieManager definition.
   *
   * @var \Drupal\path_alias_entities\pathAliasEntitieManager
   */
  protected $pathAliasEntitieManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->pathAliasEntitieManager = $container->get('path_alias.entities');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  // public function blockAccess(AccountInterface $account) {
  //   if ($this->getSubsiteFromContext()) {
  //     return AccessResult::allowed();
  //   }

  //   return AccessResult::neutral();
  // }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $path_entities = $this->pathAliasEntitieManager->getPathAliasEntities();

    if (empty($path_entities)) {
      return $build;
    }

    $first_path_entity = reset($path_entities);

    // Only continue if the first entity in the path alias is a Publication
    // term.
    if ($first_path_entity->getEntityType()->id() !== 'taxonomy_term' || $first_path_entity->bundle() !== 'publication') {
      return $build;
    }

    if ($first_path_entity->field_inline_svg_logo->isEmpty()) {
      return $build;
    }

    $build['publication_logo'] = $first_path_entity->field_inline_svg_logo->view('default');
    $build['publication_logo']['#attributes']['class'][] = 'publication-logo';

    $last_path_entity = end($path_entities);

    if ($last_path_entity->getEntityType()->id() === 'collection' && $last_path_entity->bundle() === 'publication_issue') {
      $build['publication_issue_title'] = [
        '#markup' => '<div class="publication-logo--issue">' . $last_path_entity->label() . '</div>',
        '#weight' => 10,
      ];
    }
    elseif ($last_path_entity->getEntityType()->id() === 'node' && $last_path_entity->bundle() === 'story') {
      $build['publication_logo']['#attributes']['class'][] = 'publication-logo--small';

      $build['publication_subtitle'] = [
        '#markup' => '<div class="publication-logo--subtitle">Alumni Magazine</div>',
        '#weight' => 10,
      ];
    }

    // dump($build);

    $build['#cache']['max-age'] = 0; // Tmp for testing
    // dump($build);
    return $build;
  }

}
