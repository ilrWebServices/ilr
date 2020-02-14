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
   * Get the display name (not the admin label) for a Persona.
   *
   * @return string
   *   The display name, which can be modified with
   *   hook_persona_display_name_alter().
   */
  public function getDisplayName();

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
