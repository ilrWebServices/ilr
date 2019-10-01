<?php

namespace Drupal\ilr\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'CoursePageNav' block.
 *
 * @Block(
 *  id = "course_page_nav",
 *  admin_label = @Translation("Course page nav"),
 * )
 */
class CoursePageNav extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    $build = [
      '#theme' => 'ilr_course_page_nav_block',
    ];

    return $build;
  }

}
