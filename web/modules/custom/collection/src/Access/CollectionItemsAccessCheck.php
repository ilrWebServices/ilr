<?php
namespace Drupal\collection\Access;

use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\RouteMatch;

/**
 * Checks access for displaying configuration translation page.
 */
class CollectionItemsAccessCheck implements AccessInterface {

  use CollectionOwnerTrait;

  /**
   * Collection item listing access check.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @param Drupal\Core\Routing\RouteMatch $route_match
   *   The current route match. This is used to find the collection for the
   *   route being checked. This means that this access check will only work
   *   for routes with a collection parameter.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, RouteMatch $route_match) {
    if ($account->hasPermission('administer collections')) {
      return AccessResult::allowed();
    }

    $collection_entity = $route_match->getParameter('collection');

    if (!$collection_entity) {
      return AccessResult::neutral("CollectionItemsAccessCheck can only be used on routes that include the `collection` entity parameter.");
    }

    $is_owner = $this->isOwner($collection_entity, $account);

    if ($account->hasPermission('edit own collections') && $is_owner) {
      return AccessResult::allowed();
    }

    return AccessResult::neutral("The user must be an owner and have the 'edit own collections' permission.");
  }

}
