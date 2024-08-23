<?php

namespace Drupal\person\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\person\Entity\Persona;

/**
 * Event that is fired when a collection is saved.
 */
class PersonaCreateEvent extends Event {

  /**
   * The persona.
   *
   * @var \Drupal\person\Entity\Persona
   */
  public $persona;

  /**
   * Constructs the object.
   *
   * @param \Drupal\person\Entity\Persona $persona
   *   The persona being created.
   */
  public function __construct(Persona $persona) {
    $this->persona = $persona;
  }

}
