<?php

namespace Drupal\ilr\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Defines a validation constraint for menu items attached to nodes.
 *
 * @Constraint(
 *   id = "NodeMenuLevel",
 *   label = @Translation("Node menu level validator", context = "Validation"),
 * )
 */
class NodeMenuLevel extends Constraint {
  public $message = 'Please choose a parent for the menu link.';
}
