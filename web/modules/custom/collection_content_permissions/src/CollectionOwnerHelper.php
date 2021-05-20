<?php

namespace Drupal\collection_content_permissions;

use Drupal\collection\Access\CollectionOwnerTrait;

/**
 * A simple class that publicly exposes CollectionOwnerTrait::isOwner().
 */
class CollectionOwnerHelper {
  use CollectionOwnerTrait {
    isOwner as public;
  }
}
