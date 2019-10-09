<?php

namespace Drupal\ilr\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'ILRWordmarkBlock' block.
 *
 * @Block(
 *  id = "ilrwordmark_block",
 *  admin_label = @Translation("ILR Wordmark"),
 * )
 */
class ILRWordmarkBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['ilrwordmark_block']['#markup'] = 'Implemented via Union. See template.';

    return $build;
  }

}
