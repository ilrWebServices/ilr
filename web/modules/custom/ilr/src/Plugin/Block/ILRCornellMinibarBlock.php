<?php

namespace Drupal\ilr\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Cornell Minibar' Block.
 *
 * @Block(
 *   id = "ilr_cornell_minibar_block",
 *   admin_label = @Translation("Cornell minibar"),
 *   category = @Translation("ILR"),
 * )
 */
class ILRCornellMinibarBlock extends BlockBase {

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
      '#theme' => 'ilr_cornell_minibar_block',
    ];

    return $build;
  }

}
