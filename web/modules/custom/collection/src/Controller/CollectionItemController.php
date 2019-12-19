<?php

namespace Drupal\collection\Controller;

use Drupal\Core\Entity\Controller\EntityController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;

class CollectionItemController extends EntityController {

  protected function redirect($route_name, array $route_parameters = [], array $options = [], $status = 302) {
    $options['absolute'] = TRUE;
    $route_parameters['collection'] = \Drupal::routeMatch()->getRawParameter('collection');
    return new RedirectResponse(Url::fromRoute($route_name, $route_parameters, $options)->toString(), $status);
  }

  public function addPage($entity_type_id) {
    $build = parent::addPage($entity_type_id);

    if ($build instanceof RedirectResponse) {
      return $build;
    }

    foreach ($build['#bundles'] as $key => $bundle) {
      $collection_item_add_url = Url::fromRoute('entity.collection_item.add_form', [
        'collection' => \Drupal::routeMatch()->getRawParameter('collection'),
        'collection_item_type' => $key
      ]);

      $build['#bundles'][$key]['add_link']->setUrl($collection_item_add_url);
    }

    return $build;
  }

  /**
   * Provides a title callback.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Entity\EntityInterface $_entity
   *   (optional) An entity, passed in directly from the request attributes.
   *
   * @return string|null
   *   The title for the entity edit page, if an entity was found.
   */
  public function title(RouteMatchInterface $route_match, EntityInterface $_entity = NULL) {
    if ($entity = $this->doGetEntity($route_match, $_entity)) {
      return $this->t('Items for %label @collection_type collection', [
        '%label' => $entity->label(),
        '@collection_type' => $entity->bundle()
      ]);
    }
  }

  /**
   * Provides an add title callback for collection items with bundles.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle_parameter
   *   The name of the route parameter that holds the bundle.
   *
   * @return string
   *   The title for the entity add page, if the bundle was found.
   */
  public function addBundleTitle(RouteMatchInterface $route_match, $entity_type_id, $bundle_parameter) {
    $collection = $route_match->getParameter('collection');

    return $this->t('Add new item to %collection @collection_type collection', [
      '%collection' => $collection->label(),
      '@collection_type' => $collection->bundle()
    ]);
  }

}
