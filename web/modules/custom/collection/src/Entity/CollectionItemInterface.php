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
interface CollectionItemInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface
{

  /**
   * Add get/set methods for your configuration properties here.
   */

  /**
   * Gets the Collection item name.
   *
   * @return string
   *   Name of the Collection item.
   */
  public function getName();

  /**
   * Sets the Collection item name.
   *
   * @param string $name
   *   The Collection name.
   *
   * @return \Drupal\collection\Entity\CollectionItemInterface
   *   The called Collection item entity.
   */
  public function setName($name);

  /**
   * Gets the Collection item creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Collection item.
   */
  public function getCreatedTime();

  /**
   * Sets the Collection item creation timestamp.
   *
   * @param int $timestamp
   *   The Collection item creation timestamp.
   *
   * @return \Drupal\collection\Entity\CollectionItemInterface
   *   The called Collection item entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Get a key-value attribute for this collection item.
   *
   * @param string $key
   *   The key of the attribute to return.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface|FALSE
   *   The key-value attribute if it exists, FALSE if not.
   */
  public function getAttribute(string $key);

  /**
   * Add or update a key-value attribute for this collection item.
   *
   * @param string $key
   *   The attribute key.
   *
   * @param string $value
   *   The attribute value.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface
   *   The new or updated attribute.
   */
  public function setAttribute(string $key, string $value);

}
