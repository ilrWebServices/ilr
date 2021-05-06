<?php

namespace Drupal\ilr\Plugin\EntityReferenceSelection;

use Drupal\user\Plugin\EntityReferenceSelection\UserSelection;

/**
 * Overrides the default:user selection plugin to allow common name matches.
 *
 * Because of the following:
 *   - The group is 'default'
 *   - This plugin inherits DefaultSelection, which uses a deriver
 *   - The weight is higher than 'default:user'
 *   - And possibly also because the id starts with 'default:'
 *
 * ...this selection plugin will be used in place of UserSelection. This is
 * done so that autocomplete widgets will also match on field_common_name.
 *
 * @EntityReferenceSelection(
 *   id = "default:user_or_common_name",
 *   label = @Translation("User by common name selection"),
 *   entity_types = {"user"},
 *   group = "default",
 *   weight = 2
 * )
 */
class UserOrCommonNameSelection extends UserSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    // Note that we call UserSelection::buildEntityQuery() without the match
    // info. This is because it adds the name condition, and there appears to be
    // no way to remove that condition or set additional conditions to use 'or'.
    // We'll set the name or common name condition ourselves here.
    $query = parent::buildEntityQuery();

    if (isset($match)) {
      $orGroup = $query->orConditionGroup()
        ->condition('name', $match, $match_operator)
        ->condition('field_common_name', $match, $match_operator);
      $query->condition($orGroup);
    }

    return $query;
  }

}
