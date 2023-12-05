<?php

namespace Drupal\ilr\EventSubscriber;

use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Add custom headers.
 */
class ResponseEventSubscriber implements EventSubscriberInterface {

  /**
   * Register subscribed events.
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::RESPONSE => 'onResponse',
    ];
  }

  /**
   * Prevent search engine indexing when hostname isn't whitelisted.
   */
  public function onResponse(ResponseEvent $event) {
    $request = $event->getRequest();

    if (!in_array($request->getHost(), [
      'ilr.cornell.edu',
      'www.ilr.cornell.edu',
    ])) {
      $response = $event->getResponse();
      $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
    }
  }

}
