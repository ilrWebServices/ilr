<?php

namespace Drupal\collection\Access;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides an owner check for collection access control handlers.
 */
trait CollectionOwnerTrait {

  /**
   * Determine if an account is the owner of a collection.
   *
   * @param \Drupal\collection\Entity\CollectionInterface $collection
   *   The collection entity.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return bool
   *   TRUE if the account is an owner of this collection.
   */
  protected function isOwner(CollectionInterface $collection, AccountInterface $account) {
    // @todo: Check ownership via collection items that target users and have an
    // `owner` attribute, either instead of or in addition to this check.
    return ($account->id() && $account->id() === $collection->getOwnerId());
  }

}
