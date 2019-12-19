<?php

namespace Drupal\collection\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

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

}
