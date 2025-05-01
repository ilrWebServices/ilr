<?php

namespace Drupal\ilr\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'ILRSocialLinksBlock' block.
 *
 * @Block(
 *  id = "ilr_social_links_block",
 *  admin_label = @Translation("ILR Social Links"),
 * )
 */
class ILRSocialLinksBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['ilr_social_links_block']['#markup'] = 'Implemented via Union. See template.';
    return $build;
  }

}
