<?php

namespace Drupal\person;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\person\Entity\Person;
use Drupal\person\PersonaInterface;

/**
 * The persona manager service.
 */
class PersonaManager {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  private $entityTypeManager;

  /**
   * PersonaManager constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManager $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Get all personas for a given person entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return array
   *   The personas for this person.
   */
  public function getPersonas(ContentEntityInterface $person) {
    $personas = [];

    if (!$person instanceof Person) {
      return [];
    }

    // Load all personas for this person.
    $persona_storage = $this->entityTypeManager->getStorage('persona');

    $persona_ids = $persona_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('person', $person->id())
      ->execute();

    $personas = $persona_storage->loadMultiple($persona_ids);

    return $personas;
  }

  /**
   * Get the 'ilr_employee' (AKA 'profile') persona for a given person entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string|boolean $access
   *   The access level to check, or false to return all items.
   *
   * @return \Drupal\person\Entity\Person|null
   *   The default persona for this person.
   */
  public function getPersonaProfile(PersonaInterface $persona) {
    foreach ($this->getPersonas($persona->person->entity) as $persona) {
      if ($persona->bundle() === 'ilr_employee' && $persona->isPublished()) {
        return $persona;
      }
    }
    return NULL;
  }

}
