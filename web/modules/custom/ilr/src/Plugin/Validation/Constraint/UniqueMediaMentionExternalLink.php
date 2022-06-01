<?php

namespace Drupal\ilr\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that field_external.
 *
 * @Constraint(
 *   id = "UniqueMediaMentionExternalLink",
 *   label = @Translation("Unique Media Mention External Link", context = "Validation")
 * )
 */
class UniqueMediaMentionExternalLink extends Constraint {

  // The message that will be shown if the URL is used in another media mention.
  public $duplicate = 'There is an existing media mention for %url. You may wish to <a href="@create_crosspost_url">create a cross-post</a> instead.';

}
