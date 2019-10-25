<?php

namespace Drupal\summary_word_limit\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the summary of a text field is within the word count limit.
 *
 * @Constraint(
 *   id = "SummaryWordLimit",
 *   label = @Translation("Summary Word Limit", context = "Validation"),
 *   type = "string"
 * )
 */
class SummaryWordLimit extends Constraint {

  // The message that will be shown if the summary is over the word limit count.
  public $overWordLimit = 'The %field_name summary is over the limit of %limit_count words. You used %current_count words.';

}
