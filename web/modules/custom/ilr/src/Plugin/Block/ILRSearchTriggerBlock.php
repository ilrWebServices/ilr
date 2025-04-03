<?php

namespace Drupal\ilr\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'ILR Search trigger' Block.
 *
 * @Block(
 *   id = "ilr_search_trigger_block",
 *   admin_label = @Translation("ILR search trigger"),
 *   category = @Translation("ILR"),
 * )
 */
class ILRSearchTriggerBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['label_display' => FALSE];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [
      '#type' => 'inline_template',
      '#template' => '<form class="ilr-search" action="/search" method="GET"><input type="text" name="s" placeholder="{% trans %}Search{% endtrans %}" value="{{ value }}"/></form>',
      '#context' => [
        'value' => '',
      ],
    ];

    return $build;
  }

}
