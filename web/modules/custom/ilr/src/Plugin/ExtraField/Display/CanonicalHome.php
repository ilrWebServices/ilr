<?php

namespace Drupal\ilr\Plugin\ExtraField\Display;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\extra_field\Plugin\ExtraFieldDisplayBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Course Class Register Extra field Display.
 *
 * @ExtraFieldDisplay(
 *   id = "canonical_home",
 *   label = @Translation("Canonical home"),
 *   bundles = {
 *     "collection_item.blog",
 *   },
 *   visible = true
 * )
 */
class CanonicalHome extends ExtraFieldDisplayBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->pathAliasEntitiesManager = $container->get('path_alias.entities');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function view(ContentEntityInterface $collection_item) {
    $build = [];

    // If the collection item is canonical, nothing to do.
    if ($collection_item->get('canonical')->value === '1') {
      return $build;
    }

    if ($canonical_link = $this->getCanonicalLink($collection_item)) {
      $build['canonical_home'] = [
        '#markup' => $this->t('Originally published in @link', ['@link' => $canonical_link->toString()]),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ];
    }

    return $build;
  }

  /**
   * Generate the canonical link if this item is non-canonical.
   */
  protected function getCanonicalLink(ContentEntityInterface $collection_item) {
    $canonical_link = NULL;

    $canonical_collection_item_ids = $this->entityTypeManager->getStorage('collection_item')->getQuery()
      ->condition('item.entity:node.status', 1)
      ->condition('canonical', 1)
      ->condition('item.target_id', $collection_item->item->entity->id())->execute();

    if (!empty($canonical_collection_item_ids)) {
      $canonical_collection_item_id = reset($canonical_collection_item_ids);
      $canonical_collection_item = $this->entityTypeManager->getStorage('collection_item')->load($canonical_collection_item_id);
      $canonical_link = $canonical_collection_item->collection->entity->toLink();
    }

    return $canonical_link;
  }

}
