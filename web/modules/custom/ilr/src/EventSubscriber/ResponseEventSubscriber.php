<?php

namespace Drupal\ilr\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

/**
 * Add custom headers.
 */
class ResponseEventSubscriber implements EventSubscriberInterface {

  /**
   * Register subscribed events.
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::RESPONSE => 'onResponse',
    ];
  }

  /**
   * Prevent search engine indexing when hostname isn't whitelisted.
   */
  public function onResponse(FilterResponseEvent $event) {
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
