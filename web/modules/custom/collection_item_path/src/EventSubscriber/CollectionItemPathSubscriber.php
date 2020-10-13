<?php

namespace Drupal\collection_item_path\EventSubscriber;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

/**
 * Class CollectionItemPathSubscriber.
 */
class CollectionItemPathSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
   * Constructs a new MenuSubsitesSubscriber object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger, TranslationInterface $string_translation, AccountProxyInterface $current_user) {
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->stringTranslation = $string_translation;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => 'handleRedirects',
    ];
  }

  /**
   * Handle redirects for canonical collection items.
   */
  public function handleRedirects(GetResponseEvent $event) {
    $request = $event->getRequest();

    if ($request->attributes->get('_route') === 'entity.collection_item.canonical') {
      $request = $event->getRequest();
      $collection_item = $request->attributes->get('collection_item');

      if ($collection_item->isCanonical()) {
        $this->redirectToCanonicalEntity($event, $collection_item->item->entity);
      }
    }
  }

  /**
   * Redirect canonical collection items to their item.
   */
  protected function redirectToCanonicalEntity(GetResponseEvent $event, ContentEntityInterface $entity) {
    $url = $entity->toUrl();
    $link = Link::fromTextAndUrl($entity->label(), $url);

    if ($this->currentUser->hasPermission('administer collections')) {
      $this->messenger->addWarning($this->t('This is the canonical collection item, so it redirects to %link for non-administrators.', [
        '%link' => $link->toString()
      ]));
    }
    else {
      $response = new RedirectResponse($url->toString(), 308);
      $event->setResponse($response);
    }
  }

}
