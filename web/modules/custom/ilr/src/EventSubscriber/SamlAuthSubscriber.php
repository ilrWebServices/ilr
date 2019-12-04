<?php

namespace Drupal\ilr\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\Event;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\samlauth\Event\SamlauthUserSyncEvent;

/**
 * Class SamlAuthSubscriber.
 */
class SamlAuthSubscriber implements EventSubscriberInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new SamlAuthSubscriber object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events['samlauth.user_sync'] = ['samlauthUserSync'];

    return $events;
  }

  /**
   * This method is called when the samlauth.user_sync is dispatched.
   *
   * @param \Drupal\samlauth\Event\SamlauthUserSyncEvent $event
   *   The dispatched event.
   */
  public function samlauthUserSync(SamlauthUserSyncEvent $event) {
    $account = $event->getAccount();
    $saml_attributes = $event->getAttributes();

    // Assign the field_common_name value.
    $account->set('field_common_name', $saml_attributes['urn:oid:2.5.4.3']);

    // Set the account and mark it changed. The docs in SamlauthUserSyncEvent
    // specifially say to not save the account here.
    $event->setAccount($account);
    $event->markAccountChanged();
  }

}
