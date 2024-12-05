<?php

namespace Drupal\person\Plugin\Menu\LocalAction;

use Drupal\Core\Menu\LocalActionDefault;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Modifies the 'Add persona' local action.
 */
class PersonaAddLocalAction extends LocalActionDefault {

  /**
   * {@inheritdoc}
   */
  public function getOptions(RouteMatchInterface $route_match) {
    $options = parent::getOptions($route_match);

    // If the route specifies a person, append it to the query string.
    if ($person = $route_match->getRawParameter('person')) {
      $options['query']['person'] = $person;
    }

    return $options;
  }

}
