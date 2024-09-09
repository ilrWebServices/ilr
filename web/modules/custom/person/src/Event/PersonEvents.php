<?php

namespace Drupal\person\Event;

/**
 * Defines PersonEvents.
 */
final class PersonEvents {

  /**
   * Name of the event fired after a new persona entity is saved.
   *
   * @Event
   * @see \Drupal\person\Event\PersonEvents
   * @var string
   */
  const PERSONA_ENTITY_CREATE = 'persona.entity.create';

}
