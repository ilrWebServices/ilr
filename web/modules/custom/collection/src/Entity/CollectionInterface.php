<?php

namespace Drupal\collection\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides an interface for defining Collection entities.
 *
 * @ingroup collection
 */
interface CollectionInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Add get/set methods for your configuration properties here.
   */

  /**
   * Gets the Collection name.
   *
   * @return string
   *   Name of the Collection.
   */
  public function getName();

  /**
   * Sets the Collection name.
   *
   * @param string $name
   *   The Collection name.
   *
   * @return \Drupal\collection\Entity\CollectionInterface
   *   The called Collection entity.
   */
  public function setName($name);

  /**
   * Gets the Collection creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Collection.
   */
  public function getCreatedTime();

  /**
   * Sets the Collection creation timestamp.
   *
   * @param int $timestamp
   *   The Collection creation timestamp.
   *
   * @return \Drupal\collection\Entity\CollectionInterface
   *   The called Collection entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the collection items for this collection
   *
   * @return array
   *   A bunch of \Drupal\collection\Entity\CollectionItemInterface
   */
  public function getItems();

  /**
   * Get the collection item for a given entity in this collection.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   A content or config entity.
   *
   * @return \Drupal\collection\Entity\CollectionItemInterface|FALSE
   *   A collection item if this collection has one for the entity or false if
   *   not.
   */
  public function getItem(EntityInterface $entity);

  /**
   * Search a collection for items by a given entity type.
   *
   * @param string $type
   *   The entity type
   *
   * @return array
   *   A bunch of \Drupal\collection\Entity\CollectionItemInterface
   */
  public function findItems(string $type);

  /**
   * Add any entity as an item to this collection.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   A content or config entity.
   *
   * @return \Drupal\collection\Entity\CollectionItemInterface|FALSE
   *   The collection item if it was created successfully.
   */
  public function addItem(EntityInterface $entity);

  /**
   * Remove an entity from this collection.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   A content or config entity.
   *
   * @return bool
   *   TRUE if the entity was in this collection and removed successfully.
   */
  public function removeItem(EntityInterface $entity);
}
