<?php

namespace Drupal\ilr\Plugin\paragraphs\Behavior;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;

/**
 * Provides settings for columns.
 *
 * @ParagraphsBehavior(
 *   id = "column_settings",
 *   label = @Translation("Column settings"),
 *   description = @Translation("Configure column settings."),
 *   weight = 1
 * )
 */
class ColumnSettings extends ParagraphsBehaviorBase {

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $form['columns'] = [
      '#type' => 'number',
      '#title' => $this->t('Columns'),
      '#description' => $this->t('The number of columns for this content. Text will flow automatically and fill these columns. On small screens, the content will revert to a single column.'),
      '#min' => 1,
      '#max' => 4,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'columns') ?? 1,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    $columns = (int) $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'columns') ?? 1;

    if ($columns > 1) {
      $variables['attributes']['class'][] = 'cu-component--columns-' . $columns;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraphs_entity, EntityViewDisplayInterface $display, $view_mode) {}

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(Paragraph $paragraph) {
    $summary = [];

    if ($paragraph->getBehaviorSetting($this->getPluginId(), 'columns')) {
      $summary[] = [
        'label' => 'Columns',
        'value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'columns'),
      ];
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * This behavior is only applicable to paragraphs of type 'rich_text'.
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    return in_array($paragraphs_type->id(), ['rich_text']);
  }

}
