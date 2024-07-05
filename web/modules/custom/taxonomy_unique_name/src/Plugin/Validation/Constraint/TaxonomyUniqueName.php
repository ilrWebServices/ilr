<?php

namespace Drupal\taxonomy_unique_name\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that there is not an existing term with the same name.
 *
 * @Constraint(
 *   id = "TaxonomyUniqueName",
 *   label = @Translation("Unique term name", context = "Validation")
 * )
 */
class TaxonomyUniqueName extends Constraint {

  // The message that will be shown if there is an existing term with the same name.
  const DUPE_MESSAGE = 'Term %term already exists in vocabulary %vocabulary.';

  public $duplicate = self::DUPE_MESSAGE;

}

