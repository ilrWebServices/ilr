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
 *   admin_label = @Translation("Subsite branding")
 * )
 */
class SubsiteBrandingBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entities represented by the current path.
   *
   * @var array
   */
  protected $pathEntities;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->pathEntities = $container->get('path_alias.entities')->getPathAliasEntities();
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account) {
    if ($this->getSubsiteFromPath()) {
      return AccessResult::allowed();
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $subsite_collection = $this->getSubsiteFromPath();

    $build = [];
    $build['#theme'] = 'subsite_branding_block';
    $build['#subsite_path'] = $subsite_collection->toUrl();

    // If the collection has a logo, use it.
    if ($subsite_collection->hasField('logo') && !$subsite_collection->logo->isEmpty()) {
      $build['#subsite_logo'] = [
        '#theme' => 'image',
        '#uri' => $subsite_collection->logo->first()->entity->field_media_svg->entity->getFileUri(),
        '#style_name' => 'media_library',
        '#alt' => $this->t('@collection logo', ['@collection' => $subsite_collection->label()]),
        '#cache' => [
          'contexts' => [
            'url.path',
          ],
        ],
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
      $build['#subsite_name'] = $subsite_collection->full_name->value;
    }

    return $build;
  }

  /**
   * Return the first subsite found in the path entities.
   */
  protected function getSubsiteFromPath() {
    foreach ($this->pathEntities as $entity) {
      if ($entity instanceof CollectionInterface) {
        $collection_type = $this->entityTypeManager->getStorage('collection_type')->load($entity->bundle());
        if ((bool) $collection_type->getThirdPartySetting('collection_subsites', 'contains_subsites')) {
          return $entity;
        }
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $cache_tags = parent::getCacheTags();

    if ($subsite_collection = $this->getSubsiteFromPath()) {
      $cache_tags = Cache::mergeTags($cache_tags, $subsite_collection->getCacheTags());
    }

    return $cache_tags;
  }

}
