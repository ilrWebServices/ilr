<?php

namespace Drupal\collection_item_path\Controller;

use Drupal\Core\Entity\Controller\EntityController;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\collection\Entity\CollectionItemInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for CollectionItem routes.
 */
class CollectionItemPathController extends EntityController {

  /**
   * Redirects to the appropriate content edit form.
   *
   * Also sets a return destination to the current collection item.
   *
   * @param \Drupal\collection\Entity\CollectionInterface $collection
   *   The item's collection.
   * @param \Drupal\collection\Entity\CollectionItemInterface $collection_item
   *   The collection item.
   *
   * @return Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response object.
   */
  public function itemEntityEdit(CollectionInterface $collection, CollectionItemInterface $collection_item) {
    $return_destination = $collection_item->toUrl()->toString();
    return new RedirectResponse($collection_item->item->entity->toURL('edit-form', ['query' => ['destination' => $return_destination]])->toString());
  }

}
