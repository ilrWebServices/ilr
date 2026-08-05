<?php

declare(strict_types=1);

namespace Drupal\ilr_unified_programs\Entity\Node;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\node\Entity\Node;

/**
 * A base bundle class for node entities.
 */
abstract class ProgramNodeBase extends Node {

  public function getNextStartDate(): DrupalDateTime {
    return new DrupalDateTime();
  }

}
