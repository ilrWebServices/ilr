<?php

namespace Drupal\ilr\EventSubscriber;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\EventSubscriber\HttpExceptionSubscriberBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\redirect\RedirectRepository;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Unpublished Nodes Redirect On 403 Subscriber class.
 */
class Unpublished403Subscriber extends HttpExceptionSubscriberBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The redirect repository service
   *
   * @var \Drupal\redirect\RedirectRepository
   */
  protected $redirectRepository;

  /**
   * {@inheritdoc}
   */
  protected function getHandledFormats() {
    return ['html'];
  }

  /**
   * Create a new Unpublished403Subscriber object.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\redirect\RedirectRepository $redirect_repository
   *   The redirect repository service.
   */
  public function __construct(AccountProxyInterface $current_user, RedirectRepository $redirect_repository) {
    $this->currentUser = $current_user;
    $this->redirectRepository = $redirect_repository;
  }

  /**
   * Handles 403 codes on unpublished content entities.
   *
   * This assumes that redirects for existing paths are normally ignored.
   * Instead, this will redirect an unpublished content entity if a redirect for
   * its path is found, as opposed to the 403 Access Denied that is usually
   * returned for unpublished content.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   */
  public function on403(ExceptionEvent $event) {
    $request = $event->getRequest();

    /** @var \Drupal\Core\entity\EntityPublishedInterface $entity */
    $entity = $request->attributes->get('_entity') ?? $request->attributes->get('node');

    if (!$entity instanceof EntityPublishedInterface) {
      return;
    }

    if ($entity->isPublished()) {
      return;
    }

    if (!$this->currentUser->isAnonymous()) {
      return;
    }

    // Search for a redirect.
    $redirects = $this->redirectRepository->findBySourcePath(substr($entity->toUrl()->toString(), 1));

    if ($redirects) {
      $redirect = reset($redirects);
      $metadata = CacheableMetadata::createFromObject($entity)
        ->addCacheTags(['rendered'])
        ->merge(CacheableMetadata::createFromObject($redirect));
      $response = new TrustedRedirectResponse($redirect->getRedirectUrl()->toString(), $redirect->getStatusCode());
      $response->addCacheableDependency($metadata);

      // Set response as not cacheable, otherwise browser will cache it.
      $response->setCache(['max_age' => 0]);
      $event->setResponse($response);
    }

    // If there are no redirects, we could consider returning a 404 instead of
    // this 403. Or we could set the 403 text to something other than access
    // denied.
    // else {
    //   $event->setThrowable(new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Unpublished'));
    // }
  }

}
