<?php

namespace Drupal\ilr\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\person\Event\PersonEvents;

/**
 * Subscriber for events related to collection entities.
 */
class PersonEventSubscriber implements EventSubscriberInterface {

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
    $persona = $event->getPersona();

    // If this is an ilr_employee persona with the role 'Faculty', add a
    // publications paragraph.
    if ($persona->bundle() === 'ilr_employee' && $persona->field_employee_role->entity->name->value === 'Faculty') {
      /** @var \Drupal\paragraphs\ParagraphInterface $publications */
      $publications = $this->entityTypeManager->getStorage('paragraph')->create([
        'type' => 'publications',
      ]);

      if ($persona->hasField('field_netid') && $persona->get('field_netid')) {
        $settings = [
          'remote_publications' => ['netid' => $persona->field_netid->value],
        ];

        $publications->setAllBehaviorSettings($settings);
        $publications->save();
      }

      $persona->field_components->appendItem($publications);
    }
  }

}
