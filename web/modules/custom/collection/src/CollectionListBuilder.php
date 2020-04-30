<?php

namespace Drupal\collection;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of Collection entities.
 *
 * @ingroup collection
 */
class CollectionListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function load() {
    $entity_ids = $this->getEntityIds();
    $loaded_collections = $this->storage->loadMultiple($entity_ids);
    $displayed_collections = [];

    foreach ($loaded_collections as $collection) {
      if ($collection->access('view')) {
        $displayed_collections[] = $collection;
      }
    }

    return $displayed_collections;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Name');
    $header['type'] = $this->t('Type');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var \Drupal\collection\Entity\Collection $entity */
    $row['name'] = $entity->toLink();
    $row['type'] = $entity->bundle();
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function buildOperations(EntityInterface $entity) {
    $build = parent::buildOperations($entity);

    $collection_items_url = Url::fromRoute(
      'entity.collection_item.collection',
      ['collection' => $entity->id()]
    );

    // Add a link to the collection items listing.
    $build['#links'] = array_merge([
      'view_items' => [
        'title' => $this->t('Items'),
        'url' => $collection_items_url,
        'weight' => -1,
      ]
    ], $build['#links']);

    return $build;
  }

}
