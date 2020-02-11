<?php

namespace Drupal\person;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\EntityPublishedInterface;

/**
 * Provides an interface for defining Person entities.
 *
 * @ingroup person
 */
interface PersonaInterface extends ContentEntityInterface, EntityChangedInterface, RevisionLogInterface, EntityPublishedInterface {

  /**
   * Get a list of names of fields whose values this Persona inherits.
   *
   * @return array
   *   A list of names of inherited fields for this Persona.
   */
  public function getInheritedFieldNames();

  /**
   * Checks if a field value is overridden from the person.
   *
   * @param string $field_name
   *
   * @return boolean
   *   TRUE if the value is overridden.
   */
  public function fieldIsOverridden($field_name);

}
