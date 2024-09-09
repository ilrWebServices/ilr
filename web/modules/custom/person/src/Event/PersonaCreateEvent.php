<?php

namespace Drupal\person\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\person\Entity\Persona;

/**
 * Event that is fired when a persona is created and before it is saved.
 */
class PersonaCreateEvent extends Event {

  /**
   * Constructs the object.
   */
  public function __construct(
    protected Persona $persona
  ) {}

  public function getPersona() {
    return $this->persona;
  }

}
