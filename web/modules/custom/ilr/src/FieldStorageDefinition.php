<?php

namespace Drupal\ilr;

use Drupal\Core\Field\BaseFieldDefinition;

/**
 * A custom field storage definition class.
 *
 * For convenience we extend from BaseFieldDefinition although this should not
 * implement FieldDefinitionInterface.
 *
 * @see core/modules/system/tests/modules/entity_test/src/FieldStorageDefinition.php
 *
 * @todo Provide and make use of a proper FieldStorageDefinition class instead:
 *   https://www.drupal.org/node/2280639.
 */
class FieldStorageDefinition extends BaseFieldDefinition {

  /**
   * {@inheritdoc}
   */
  public function isBaseField() {
    return FALSE;
  }

}
