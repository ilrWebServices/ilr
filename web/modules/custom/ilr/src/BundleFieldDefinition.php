<?php

namespace Drupal\ilr;

use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Provides a field definition class for bundle fields.
 *
 * Core currently doesn't provide one, the hook_entity_bundle_field_info()
 * example uses BaseFieldDefinition, which is wrong. Tracked in #2346347.
 *
 * Note that this class implements both FieldStorageDefinitionInterface and
 * FieldDefinitionInterface. This is a simplification for DX reasons,
 * allowing code to return just the bundle definitions instead of having to
 * return both storage definitions and bundle definitions.
 *
 * @see https://git.drupalcode.org/project/entity/-/blob/8.x-1.x/src/BundleFieldDefinition.php
 */
class BundleFieldDefinition extends BaseFieldDefinition {

  /**
   * {@inheritdoc}
   */
  public function isBaseField() {
    return FALSE;
  }

}
