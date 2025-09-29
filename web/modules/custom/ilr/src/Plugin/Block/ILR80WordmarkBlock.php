<?php

namespace Drupal\ilr\Plugin\Block;

/**
 * Provides a 'ILRWordmark80Block' block.
 *
 * @Block(
 *  id = "ilr80wordmark_block",
 *  admin_label = @Translation("ILR 80th Wordmark"),
 * )
 */
class ILR80WordmarkBlock extends ILRWordmarkBlock {

  /**
   * {@inheritdoc}
   *
   */
  public function build() {
    $build = [];
    $build['ilr80wordmark_block']['#markup'] = 'See template.';

    return $build;
  }

}
