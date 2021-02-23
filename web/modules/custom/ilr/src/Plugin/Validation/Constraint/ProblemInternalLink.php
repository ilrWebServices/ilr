<?php

namespace Drupal\ilr\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Defines a validation constraint for problem internal links.
 *
 * @Constraint(
 *   id = "ProblemInternalLink",
 *   label = @Translation("Problematic internal links", context = "Validation"),
 * )
 */
class ProblemInternalLink extends Constraint {
  public $messageInternalHost = "The link '@uri' is invalid. Please do not use the internal host %host.";
  public $messageNodePath = "The link '@uri' is invalid. Please do not use internal paths such as %path.";
}
