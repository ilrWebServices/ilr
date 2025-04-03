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
      '#template' => '<form class="ilr-search" action="/search" method="GET"><input type="text" name="s" placeholder="{% trans %}Search{% endtrans %}" value="{{ value }}"/><button aria-label="Search" title="Search"><svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" height="24" viewBox="0 0 24 24" width="24" focusable="false" aria-hidden="true" style="pointer-events: none; display: inherit; width: 100%; height: 100%;"><path clip-rule="evenodd" d="M16.296 16.996a8 8 0 11.707-.708l3.909 3.91-.707.707-3.909-3.909zM18 11a7 7 0 00-14 0 7 7 0 1014 0z" fill-rule="evenodd"></path></svg></button></form>',
      '#context' => [
        'value' => '',
      ],
    ];

    return $build;
  }

}
