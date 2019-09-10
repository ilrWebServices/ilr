<?php

namespace Drupal\ilr\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\TitleBlockPluginInterface;
use Drupal\Core\Breadcrumb\Breadcrumb;

/**
 * Provides a 'CourseBannerBlock' block.
 *
 * @Block(
 *  id = "course_banner_block",
 *  admin_label = @Translation("Course banner block"),
 * )
 */
class CourseBannerBlock extends BlockBase implements TitleBlockPluginInterface {

  /**
   * The page title: a string (plain title) or a render array (formatted title).
   *
   * @var string|array
   */
  protected $title = '';

  /**
   * {@inheritdoc}
   */
  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $node = \Drupal::routeMatch()->getParameter('node');

    $build = [
      'summary' => $node->body->summary,
      '#attributes' => [
        'class' => [
          "course-banner",
        ],
      ],
    ];

    return $build;
  }

}
