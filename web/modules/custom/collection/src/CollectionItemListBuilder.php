<?php

namespace Drupal\collection;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Collection items.
 *
 * @ingroup collection
 */
class CollectionItemListBuilder extends EntityListBuilder {

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
    /* @var \Drupal\collection\Entity\CollectionItem $entity */
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.collection_item.canonical',
      ['collection_item' => $entity->id()]
    );
    $row['type'] = $entity->bundle();
    return $row + parent::buildRow($entity);
  }

}
