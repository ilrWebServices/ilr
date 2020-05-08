<?php

namespace Drupal\ilr\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteMatch;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Salesforce ID to Course redirect controller.
 */
class CourseSalesforceIdRedirect extends ControllerBase {

  /**
   * Returns a redirect if the sfid in the route is mapped to a node.
   */
  public function response(RouteMatch $route_match) {
    $mapped_objects = static::entityTypeManager()->getStorage('salesforce_mapped_object')->loadByProperties([
      'salesforce_mapping' => 'course_node',
      'salesforce_id' => $route_match->getRawParameter('sfid'),
    ]);

    if ($mapped_objects) {
      /** @var \Drupal\salesforce_mapping\Entity\MappedObject $mapped_object */
      $mapped_object = reset($mapped_objects);
      $url = $mapped_object->getMappedEntity()->toUrl();
      return $this->redirect($url->getRouteName(), $url->getRouteParameters());
    }

    throw new NotFoundHttpException();
  }

}
