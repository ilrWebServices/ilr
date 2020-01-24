<?php

namespace Drupal\collection\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;

/**
 * Defines dynamic local tasks for collections.
 */
class DynamicLocalActions extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $local_actions = [];

    // %todo: Only add if the collection allows nodes.
    if (TRUE) {
      $local_actions[] = [
        'id' => 'entity.collection.add_new_content_form',
        'title' => 'Add new content',
        'route_name' => 'collection_item.new.node',
      ];
    }

    foreach ($local_actions as $local_action) {
      $this->derivatives[$local_action['id']] = $base_plugin_definition;
      $this->derivatives[$local_action['id']]['title'] = $local_action['title'];
      $this->derivatives[$local_action['id']]['route_name'] = $local_action['route_name'];
      $this->derivatives[$local_action['id']]['appears_on'] = [
        'entity.collection_item.collection'
      ];
    }

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
