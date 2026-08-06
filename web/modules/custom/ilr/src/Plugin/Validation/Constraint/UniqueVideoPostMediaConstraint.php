<?php

declare(strict_types=1);

namespace Drupal\ilr\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Provides an Unique media item per Video Post constraint.
 *
 * @Constraint(
 *   id = "UniqueVideoPostMedia",
 *   label = @Translation("Unique media item per Video Post", context = "Validation"),
 * )
 *
 * @see https://www.drupal.org/node/2015723.
 */
final class UniqueVideoPostMediaConstraint extends Constraint {

  public string $message = 'There is an existing video post for this media. You may wish to <a href="@create_crosspost_url">create a cross-post</a> instead.';

}
