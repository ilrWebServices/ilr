<?php

declare(strict_types=1);

namespace Drupal\ilr_employee_position;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining an ilr employee position entity type.
 */
interface ILREmployeePositionInterface extends ContentEntityInterface, EntityChangedInterface {

}
