<?php

namespace Drupal\collection_request\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\collection\CollectionContentManager;
use Drupal\collection\Event\CollectionEvents;
use Drupal\collection\Event\CollectionItemCreateEvent;
use Drupal\collection\Entity\CollectionItemInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Listen and act on events relevant to the collection requests.
 */
class CollectionRequestSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The collection content manager service.
   *
   * @var \Drupal\collection\CollectionContentManager
   */
  protected $collectionContentManager;

  /**
   * Mail manager service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Symfony\Component\HttpFoundation\RequestStack definition.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new CollectionRequestSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\collection\CollectionContentManager $collection_content_manager
   *   The collection content manager.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   Mail manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, CollectionContentManager $collection_content_manager, MailManagerInterface $mail_manager, MessengerInterface $messenger, TranslationInterface $string_translation, AccountProxyInterface $current_user, RequestStack $request_stack) {
    $this->entityTypeManager = $entity_type_manager;
    $this->collectionContentManager = $collection_content_manager;
    $this->mailManager = $mail_manager;
    $this->messenger = $messenger;
    $this->stringTranslation = $string_translation;
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      CollectionEvents::COLLECTION_ITEM_ENTITY_CREATE => 'collectionItemCreate',
    ];
  }

  /**
   * Process the COLLECTION_CREATE event.
   *
   * @param \Drupal\collection\Event\CollectionItemCreateEvent $event
   *   The dispatched event.
   */
  public function collectionItemCreate(CollectionItemCreateEvent $event) {
    $collection_type = $this->entityTypeManager->getStorage('collection_type')->load($event->collectionItem->collection->entity->bundle());
    $send_notifications = (bool) $collection_type->getThirdPartySetting('collection_request', 'send_notifications');

    if ($send_notifications === FALSE) {
      return;
    }

    if (!$event->collectionItem->getAttribute('collection-request-uid')) {
      return;
    }

    // Prevent sending notifications if the requester is also an owner. The UI
    // for requests is a handy way of adding content to a collection. Therefore,
    // assume that the 'requester' is simply creating a non-canonical collection
    // item if they are also an owner of the target collection.
    if (in_array($this->currentUser->id(), $event->collectionItem->collection->entity->getOwnerIds())) {
      return;
    }

    if ($success = $this->sendNotifications($event->collectionItem)) {
      $this->messenger->addMessage($this->t('Your request has been sent to the owner(s) of this collection.'));
    }
  }

  /**
   * Send the notification.
   *
   * @param Drupal\collection\Entity\CollectionItemInterface $collection_item
   *   The collection item triggering the event.
   */
  protected function sendNotifications(CollectionItemInterface $collection_item) {
    $new_collection = $collection_item->collection->entity;
    $canonical_collection = $this->getCanonicalCollectionForEntity($collection_item->item->entity);
    $recipient_array = [];

    if (!$canonical_collection) {
      return;
    }

    foreach ($new_collection->user_id as $user) {
      $recipient_array[] = $user->entity->label() . ' <' . $user->entity->getEmail() . '>';
    }

    $params = [
      'id' => 'notification',
      'from' => '',
      'reply-to' => NULL,
      'subject' => $this->t('New request to add content to @collection', [
        '@collection' => $new_collection->label(),
      ]),
      'langcode' => $this->currentUser->getPreferredLangcode(),
      'body' => [
        $this->t('@user_name has requested that "@title", content from @canonical_collection, be added to @collection.', [
          '@user_name' => $collection_item->getOwner()->getDisplayName(),
          '@title' => $collection_item->item->entity->label(),
          '@canonical_collection' => $canonical_collection->label(),
          '@collection' => $collection_item->collection->entity->label(),
        ]),
        $this->t('Please approve or deny the request at @url', [
          '@url' => $collection_item->toUrl('edit-form', ['absolute' => TRUE])->toString(),
        ]),
      ],
    ];

    if ($note = $collection_item->getAttribute('collection-request-note')) {
      $params['body'][] = $this->t('Included note') . ":\r\n" . $note->value;
    }

    $message = $this->mailManager->mail('collection_request', $params['id'], implode(', ', $recipient_array), $params['langcode'], $params, $params['reply-to']);
    return (bool) $message['result'];
  }

  /**
   * Get the canonical collection for a given entity.
   *
   * Ideally, the CollectionContentManager service would offer this ability.
   */
  protected function getCanonicalCollectionForEntity(EntityInterface $entity) {
    foreach ($this->collectionContentManager->getCollectionItemsForEntity($entity) as $collection_item) {
      if ($collection_item->isCanonical()) {
        return $collection_item->collection->entity;
      }
    }
    return FALSE;
  }

}
