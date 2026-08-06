<?php

namespace Drupal\ilr\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that remote media url is unique.
 *
 * @Constraint(
 *   id = "UniqueMediaOembedVideo",
 *   label = @Translation("Unique remote media URL", context = "Validation")
 * )
 */
class UniqueMediaOembedVideo extends Constraint {

  /**
   * The message that will be shown if the URL is used in another remote media entity (e.g. video post).
   */
  public $duplicate = 'There is an existing oEmbed video for %url.';

}
