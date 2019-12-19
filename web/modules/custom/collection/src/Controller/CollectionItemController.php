<?php

namespace Drupal\collection\Controller;

use Drupal\Core\Entity\Controller\EntityController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;

class CollectionItemController extends EntityController {

  /**
   * Returns a redirect response object for the specified route.
   *
   * This is overridden from EntityController because we need to ensure that the
   * `collection` parameter is included in the redirect response. This method is
   * only called from the parent of $this->addPage.
   *
   * @param string $route_name The name of the route to which to redirect.
   * @param array $route_parameters (optional) Parameters for the route.
   * @param array $options (optional) An associative array of additional
   *   options.
   * @param int $status (optional) The HTTP redirect status code for the
   *   redirect. The default is 302 Found.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse A redirect
   *   response object that may be returned by the controller.
   */
  protected function redirect($route_name, array $route_parameters = [], array $options = [], $status = 302) {
    $options['absolute'] = TRUE;
    $route_parameters['collection'] = \Drupal::routeMatch()->getRawParameter('collection');
    return new RedirectResponse(Url::fromRoute($route_name, $route_parameters, $options)->toString(), $status);
  }

  /**
   * Displays add links for the available collection item bundles.
   *
   * Redirects to the add form if there's only one bundle available.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|array
   *   If there's only one available bundle, a redirect response.
   *   Otherwise, a render array with the add links for each bundle.
   */
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
   * Provides a collection item list title callback.
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
