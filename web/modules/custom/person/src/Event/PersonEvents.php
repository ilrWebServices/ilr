<?php

namespace Drupal\person\Event;

/**
 * Defines the CollectionEvents.
 */
final class PersonEvents {

  /**
   * Name of the event fired after a new person entity is saved.
   *
   * @Event
   * @see \Drupal\person\Event\PersonEvents
   * @var string
   */
  const PERSON_ENTITY_CREATE = 'person.entity.create';

  /**
   * Name of the event fired after a new person entity is saved.
   *
   * @Event
   * @see \Drupal\person\Event\PersonEvents
   * @var string
   */
  const PERSON_ENTITY_UPDATE = 'person.entity.update';

  /**
   * Name of the event fired after a new persona entity is saved.
   *
   * @Event
   * @see \Drupal\person\Event\PersonEvents
   * @var string
   */
  const PERSONA_ENTITY_CREATE = 'persona.entity.create';

  /**
   * Name of the event fired after a new persona entity is saved.
   *
   * @Event
   * @see \Drupal\person\Event\PersonEvents
   * @var string
   */
  const PERSONA_ENTITY_UPDATE = 'persona.entity.update';
}
