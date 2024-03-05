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
   *   The machine name of the field.
   *
   * @return bool
   *   TRUE if the value is overridden.
   */
  public function fieldIsOverridden($field_name);

  /**
   * Returns the persona default status.
   *
   * @return bool
   *   TRUE if the persona is default.
   */
  public function isDefault();

  /**
   * Sets the persona default status.
   *
   * @param bool $default
   *   TRUE to make this persona default, FALSE to remove it as the default.
   *
   * @return $this
   *   The called persona entity.
   */
  public function setDefault($default);
}
