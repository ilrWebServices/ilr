<?php

namespace Drupal\ilr\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\person\Event\PersonEvents;

/**
 * Subscriber for events related to collection entities.
 */
class PersonEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new PersonEventSubscriber object.
   */
  public function __construct(
    public EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PersonEvents::PERSONA_ENTITY_CREATE => 'personaCreate',
    ];
  }

  /**
   * Process the PERSONA_ENTITY_CREATE event.
   *
   * @param \Drupal\Component\EventDispatcher\Event $event
   *   The dispatched event.
   */
  public function personaCreate(Event $event) {
    /** @var \Drupal\person\Event\PersonaCreateEvent $event */
    $persona = $event->persona;

    if (!$persona->person->entity) {
      // Try to load a person with the a matching Netid.
      $netid = ($persona->hasField('field_netid')) ? $persona->field_netid->value : NULL;

      if ($persona->hasField('field_netid') && $netid = $persona->field_netid->value) {
        $matches = $this->entityTypeManager->getStorage('person')
          ->loadByProperties([
            'field_netid' => $netid,
        ]);

        if (!empty($matches)) {
          $event->persona->person = reset($matches);
          $violations = $persona->validate();
          if (!empty($violations)) {
            $violation_array = reset($violations);
            $violation = reset($violation_array);

            // @todo Fail elegantly rather than throwing an exception.
            throw new \Exception(t($violation->getMessage()->__toString()));
          }
        }
      }

      // Next check for person entities with same/similar(?) first and last
      // names, or perhaps display names.
      // @todo Implement.
    }
  }

}
