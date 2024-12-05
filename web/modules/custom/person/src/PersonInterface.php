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
interface PersonInterface extends ContentEntityInterface, EntityChangedInterface, RevisionLogInterface, EntityPublishedInterface {

  /**
   * Get the display name (not the admin label) for a Persona.
   *
   * @return string
   *   The display name, which can be modified with
   *   hook_person_display_name_alter().
   */
  public function getDisplayName();

}
