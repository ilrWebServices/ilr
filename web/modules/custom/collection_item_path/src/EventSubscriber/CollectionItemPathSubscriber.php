<?php

namespace Drupal\collection_item_path\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

/**
 * Class CollectionItemPathSubscriber.
 */
class CollectionItemPathSubscriber implements EventSubscriberInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new MenuSubsitesSubscriber object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
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
        $response = new RedirectResponse($collection_item->item->entity->toUrl()->toString(), 308);
        $event->setResponse($response);
      }
    }
  }

}
