<?php

namespace Drupal\ilr\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'ClassRegisterBlock' block.
 *
 * @Block(
 *  id = "class_register_block",
 *  admin_label = @Translation("Class register"),
 * )
 */
class ClassRegisterBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    if (!$node = \Drupal::routeMatch()->getParameter('node')) {
      return $build;
    }

    if ($node->bundle() != 'course' || !$node->hasField('field_topics')) {
      return $build;
    }

    $build = [
      '#theme' => 'ilr_class_register_block',
      '#classes' => $node->classes->referencedEntities(),
    ];

    return $build;
  }

}
