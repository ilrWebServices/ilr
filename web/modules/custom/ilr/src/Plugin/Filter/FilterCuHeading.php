<?php

namespace Drupal\ilr\Plugin\Filter;

use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\FilterProcessResult;

/**
 * @Filter(
 *   id = "filter_cu_heading",
 *   title = @Translation("CU Heading Filter"),
 *   description = @Translation("Add the .cu-heading class to HTML heading elements."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
 * )
 */
class FilterCuHeading extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $text = preg_replace('/<h(\d) class="/', '<h$1 class="cu-heading ', $text);
    $text = preg_replace('/<h(\d)>/', '<h$1 class="cu-heading">', $text);
    return new FilterProcessResult($text);
  }

}
