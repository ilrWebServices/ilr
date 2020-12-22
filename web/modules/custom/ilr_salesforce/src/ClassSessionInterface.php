<?php

namespace Drupal\ilr_salesforce\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface for defining Collection entities.
 *
 * @ingroup ilr_salesforce
 */
interface ClassSessionInterface extends ContentEntityInterface {

  /**
   * Add get/set methods for your configuration properties here.
   */

  /**
   * Gets the Class session title.
   *
   * @return string
   *   Title of the Class session.
   */
  public function getTitle();

  /**
   * Sets the Class session title.
   *
   * @param string $title
   *   The Class session title.
   *
   * @return \Drupal\ilr_salesforce\Entity\ClassSessionInterface
   *   The called Class session entity.
   */
  public function setTitle($title);

}
