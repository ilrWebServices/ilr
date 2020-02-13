<?php

namespace Drupal\collection;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

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
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.collection_item.collection',
      ['collection' => $entity->id()]
    );
    $row['type'] = $entity->bundle();
    return $row + parent::buildRow($entity);
  }

}
