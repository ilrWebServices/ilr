<?php

namespace Drupal\ilr\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\samlauth\Event\SamlauthUserSyncEvent;

/**
 * Subscriber for samlauth events.
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
  public static function getSubscribedEvents(): array {
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

    // TODO: Set the mail field from SAML data if the account value is empty.

    // Set the account and mark it changed. The docs in SamlauthUserSyncEvent
    // specifially say to not save the account here.
    $event->setAccount($account);
    $event->markAccountChanged();
  }

}
