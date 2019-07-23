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
    $header['collection'] = $this->t('Collection');
    $header['item'] = $this->t('Item');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var \Drupal\collection\Entity\CollectionItem $entity */
    $row['name'] = $entity->toLink();
    $row['type'] = $entity->bundle();
    $row['collection'] = $entity->collection->entity->toLink();
    // $row['item'] = $entity->item->entity->toLink();
    $row['item'] = Link::fromTextAndUrl(
      $entity->item->entity->label(),
      $entity->item->entity->toURL()
    );
    return $row + parent::buildRow($entity);
  }

}
