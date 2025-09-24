<?php

namespace Drupal\collection_content_permissions\EventSubscriber;

use Drupal\collection\CollectionContentManager;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\EventSubscriber\HttpExceptionSubscriberBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Restricts access to content in restricted collections.
 */
class CcpDefaultExceptionHtmlSubscriber extends HttpExceptionSubscriberBase {

  /**
   * Constructs a CcpDefaultExceptionHtmlSubscriber object.
   */
  public function __construct(
    protected RedirectDestinationInterface $redirectDestination,
    protected MessengerInterface $messenger,
    protected CollectionContentManager $collectionContentManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  protected function getHandledFormats() {
    return ['html'];
  }

  /**
   * Handles 403 codes for restricted collections and their collected content.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   */
  public function on403(ExceptionEvent $event) {
    $request = $event->getRequest();

    /** @var \Drupal\Core\entity\EntityPublishedInterface $entity */
    $entity = $request->attributes->get('_entity') ?? $request->attributes->get('node');

    if (!$entity instanceof ContentEntityInterface) {
      return;
    }

    // Check if the entity is a restricted collection, or in a restricted collection.
    if ($entity instanceof CollectionInterface && $entity->bundle() === 'restricted') {
      $this->setLoginMessage($entity);
      return;
    }

    $collection_items = $this->collectionContentManager->getCollectionItemsForEntity($entity, FALSE);

    foreach ($collection_items as $collection_item) {
      if ($collection_item->isCanonical() && $collection_item->collection->entity->bundle() === 'restricted') {
        $this->setLoginMessage($collection_item->item->entity);
        return;
      }
    }
  }

  /**
   * Adds a login messsage with destination parameter back to the current url.
   *
   * @param ContentEntityInterface $entity
   * @return void
   */
  private function setLoginMessage(ContentEntityInterface $entity) {
    $redirect_url = Url::fromRoute(
      'samlauth.saml_controller_login',
      [],
      ['query' => $this->redirectDestination->getAsArray()]
    );

    $this->messenger->addWarning(t('You must be <a href="@url">logged in</a> to view %content_name.', [
      '@url' => $redirect_url->toString(),
      '%content_name' => $entity->label(),
    ]));

  }

}
