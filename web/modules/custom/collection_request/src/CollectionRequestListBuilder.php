<?php

namespace Drupal\collection_request;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * .
 *
 * @ingroup collection
 */
class CollectionRequestListBuilder extends EntityListBuilder {

  protected $collectionItems;

  /**
   * Constructs a new CollectionRequestListBuilder object.
   *
   * @param array $collection_items
   *   An array of collection item entities.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   */
  public function __construct(array $collection_items, EntityTypeInterface $entity_type) {
    $this->collectionItems = $collection_items;
    $this->entityType = $entity_type;
  }

  /**
   * Loads entity IDs using a pager sorted by the entity id.
   *
   * @return array
   *   An array of entity IDs.
   */
  public function load() {
    return $this->collectionItems;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['item'] = $this->t('Item');
    $header['collection'] = $this->t('Collection');
    $header['uid'] = $this->t('Requested by');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['item'] = $entity->toLink();
    $row['collection'] = $entity->collection->entity->toLink();
    $uid = $entity->getAttribute('collection-request-uid')->value;

    if (!$uid) {
      return;
    }

    $user_storage = \Drupal::entityTypeManager()->getStorage('user');
    $requester = $user_storage->load($uid);
    $row['uid'] = $requester->label();
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['table']['#empty'] = $this->t('No pending requests.');
    return $build;
  }

}
