<?php

namespace Drupal\ilr\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'ClassRegisterBlock' block.
 *
 * @Block(
 *  id = "class_register_block",
 *  admin_label = @Translation("Class register block"),
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

    if (!$node->hasField('field_topics')) {
      return $build;
    }

    $upcoming_classes_result = views_get_view_result('next_class_course', 'block', $node->id());

    $classes = array_column($upcoming_classes_result, '_entity');

    $build = [
      '#theme' => 'ilr_class_register_block',
      '#classes' => $classes,
    ];

    return $build;
  }

}
