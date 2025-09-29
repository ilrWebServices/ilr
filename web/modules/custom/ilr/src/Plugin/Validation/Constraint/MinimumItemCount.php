<?php

namespace Drupal\ilr\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Defines a validation constraint for minimum item count on unlimited fields.
 *
 * @Constraint(
 *   id = "MinimumItemCount",
 *   label = @Translation("Paragraph carousel/gallery minimum item count validator", context = "Validation"),
 * )
 */
class MinimumItemCount extends Constraint {

  /**
   * The message shown if validation fails.
   *
   * @var string
   */
  public string $messageBelowMinimum = "A minimum of @number @item_kind items are required.";

  /**
   * Minimum required item count.
   *
   * @var int
   */
  public ?int $minItems;
}
