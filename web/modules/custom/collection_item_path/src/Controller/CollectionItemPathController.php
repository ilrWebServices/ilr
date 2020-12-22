<?php

namespace Drupal\collection_item_path\Controller;

use Drupal\Core\Entity\Controller\EntityController;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for CollectionItem routes.
 */
class CollectionItemPathController extends EntityController {

  /**
   * Redirects to the appropriate content edit form with a return destination
   * set to the current collection item.
   *
   * @param \Drupal\collection\Entity\CollectionInterface $collection
   *   The item's collection.
   * @param \Drupal\collection\Entity\CollectionItemInterface
   *   The collection item.
   *
   * @return Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function itemEntityEdit($collection, $collection_item) {
    $return_destination = $collection_item->toUrl()->toString();
    return new RedirectResponse($collection_item->item->entity->toURL('edit-form', ['query' => ['destination' => $return_destination]])->toString());
  }

}
