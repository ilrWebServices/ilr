<?php

namespace Drupal\ilr\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'RelatedCoursesBlock' block.
 *
 * @Block(
 *  id = "related_courses_block",
 *  admin_label = @Translation("Related courses block"),
 * )
 */
class RelatedCoursesBlock extends BlockBase {

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

    if (!$node->field_topics->isEmpty()) {
      $topic_item = $node->field_topics->first()->getValue();
      $tid = $topic_item['target_id'];

      $build = [
        '#theme' => 'ilr_related_courses_block',
        '#topic' => $tid,
      ];
    }

    return $build;
  }

}
