<?php

namespace Drupal\ilr\Plugin\paragraphs\Behavior;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;

/**
 * Provides a Union section settings behavior.
 *
 * @ParagraphsBehavior(
 *   id = "table_of_contents",
 *   label = @Translation("Table of contents"),
 *   description = @Translation("Behavior attached to the table of contents paragraph component."),
 *   weight = 1
 * )
 */
class TableOfContents extends ParagraphsBehaviorBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    $variables['content']['toc'] = [
      '#type' => 'container',
      '#attached' => [
        'library' => ['ilr/ilr_toc'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraphs_entity, EntityViewDisplayInterface $display, $view_mode) {}

  /**
   * {@inheritdoc}
   *
   * Rather than a summary, the admin label is rendered in the form element.
   * @see ilr_field_widget_paragraphs_form_alter().
   */
  public function settingsSummary(Paragraph $paragraph) {}

  /**
   * {@inheritdoc}
   *
   * This behavior is only applicable to paragraphs that are of type 'section'.
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    return $paragraphs_type->id() === 'table_of_contents';
  }

}
