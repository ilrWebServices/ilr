<?php

namespace Drupal\person;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining Persona type entities.
 */
interface PersonaTypeInterface extends ConfigEntityInterface {

  /**
   * Get a list of names of fields whose values this Persona type inherits.
   *
   * @return array
   *   A list of names of inherited fields for this Persona type.
   */
  public function getInheritedFieldNames();

}
