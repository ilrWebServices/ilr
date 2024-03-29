<?php

namespace Drupal\ilr_campaigns\Controller;

use Drupal\Core\Entity\Controller\EntityViewController;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines a controller to render a node in the `email` view mode.
 */
class NodeHTMLEmailController extends EntityViewController {

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $node, $view_mode = 'email', $langcode = NULL) {
    return parent::view($node, $view_mode);
  }

}
