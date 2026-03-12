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

    // Add the ilr_employee role if there's a published persona for this netid.
    if (!$account->hasRole('ilr_employee')) {
      $ilr_employee_persona = \Drupal::service('entity_type.manager')->getStorage('persona')->loadByProperties([
        'type' => 'ilr_employee',
        'status' => 1,
        'field_netid' => $saml_attributes['uid'],
      ]);

      if (!empty($ilr_employee_persona)) {
        $account->addRole('ilr_employee');
      }
    }

    // Set the account and mark it changed. The docs in SamlauthUserSyncEvent
    // specifially say to not save the account here.
    $event->setAccount($account);
    $event->markAccountChanged();
  }

}
