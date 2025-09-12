<?php

namespace Drupal\ilr\Plugin\paragraphs\Behavior;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;

/**
 * Provides settings for component layout.
 *
 * @ParagraphsBehavior(
 *   id = "layout_settings",
 *   label = @Translation("Layout settings"),
 *   description = @Translation("Configure layout settings."),
 *   weight = 1
 * )
 */
class LayoutSettings extends ParagraphsBehaviorBase {

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $form['reversed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reverse orientation'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'reversed') ?? 0,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    $reverse = (int) $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'reversed') ?? 0;

    if ($reverse) {
      $variables['attributes']['class'][] = 'cu-component--reversed';
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

    if ($paragraph->getBehaviorSetting($this->getPluginId(), 'reversed')) {
      $summary[] = [
        'label' => 'Reversed',
        'value' => '✓',
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
    return in_array($paragraphs_type->id(), ['milestone']);
  }

}
