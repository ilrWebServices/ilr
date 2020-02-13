<?php

namespace Drupal\collection_subsites\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\Core\Cache\Cache;

/**
 * Provides a 'SubsiteBrandingBlock' block.
 *
 * @Block(
 *   id = "subsite_branding_block",
 *   admin_label = @Translation("Subsite branding"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", required = FALSE),
 *     "collection" = @ContextDefinition("entity:collection", required = FALSE)
 *   }
 * )
 */
class SubsiteBrandingBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\collection_subsites\CollectionSubsitesResolver (interface?) definition.
   *
   * @var \Drupal\collection_subsites\CollectionSubsitesResolver
   */
  protected $collectionSubsitesResolver;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->collectionSubsitesResolver = $container->get('collection_subsites.resolver');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account) {
    if ($this->getSubsiteFromContext()) {
      return AccessResult::allowed();
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $subsite_collection = $this->getSubsiteFromContext();

    $build = [];
    $build['#theme'] = 'subsite_branding_block';
    $build['#subsite_path'] = $subsite_collection->toUrl();

    // If the collection has a logo, use it.
    if ($subsite_collection->hasField('logo') && !$subsite_collection->logo->isEmpty()) {
      $build['#subsite_logo'] = [
        '#theme' => 'image_style',
        '#uri' => $subsite_collection->logo->first()->entity->field_media_image->entity->getFileUri(),
        '#style_name' => 'media_library',
        '#alt' => $this->t('@collection logo', ['@collection' => $subsite_collection->label()]),
      ];
    }

    // If the collection has a slogan, use it.
    // @todo Implement this field if we decide to use this technique.
    if ($subsite_collection->hasField('slogan') && !$subsite_collection->slogan->isEmpty()) {
      $build['#subsite_slogan'] = $subsite_collection->slogan->value;
    }

    // If the collection is configured to use the name, do so.
    // @todo Implement this field if we decide to use this technique.
    if ($subsite_collection->hasField('full_name') && !$subsite_collection->full_name->isEmpty()) {
      $build['#subsite_name'] = $subsite_collection->full_name->value;;
    }

    return $build;
  }

  /**
   * Return an subsite collection entity from the current context.
   */
  protected function getSubsiteFromContext() {
    if ($collection = $this->getContextValue('collection')) {
      return $this->collectionSubsitesResolver->getSubsite($collection);
    }
    elseif ($node = $this->getContextValue('node')) {
      return $this->collectionSubsitesResolver->getSubsite($node);
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $cache_tags = parent::getCacheTags();

    if ($subsite_collection = $this->getSubsiteFromContext()) {
      $cache_tags = Cache::mergeTags($cache_tags, $subsite_collection->getCacheTags());
    }

    return $cache_tags;
  }

}
