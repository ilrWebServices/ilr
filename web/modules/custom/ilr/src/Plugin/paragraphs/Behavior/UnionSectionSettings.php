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
 *   id = "union_section_settings",
 *   label = @Translation("Union section settings"),
 *   description = @Translation("Settings related to Union sections"),
 *   weight = 1
 * )
 */
class UnionSectionSettings extends ParagraphsBehaviorBase {

  /**
   * The frame position options.
   *
   * @var array
   */
  protected $position = [
    'left' => 'Left',
    'right' => 'Right',
  ];

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $form['wide'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Wide'),
      '#min' => 1,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'wide'),
    ];

    $form['gapless'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Gapless'),
      '#description' => $this->t('Gappless sections remove the margin between components.'),
      '#min' => 1,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'gapless'),
    ];

    $form['frame_position'] = [
      '#type' => 'radios',
      '#title' => $this->t('Frame position'),
      '#options' => $this->position,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'frame_position') ?? 'left',
    ];

    $form['#weight'] = -1;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    // Check the behavior settings and set the class modifier if full width.
    if ($variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'wide')) {
      $variables['attributes']['class'] = ['cu-section--wide'];
    }

    // Check the behavior settings and set the class modifier if gapless.
    if ($variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'gapless')) {
      $variables['attributes']['class'] = ['cu-section--gapless'];
    }

    $variables['paragraph']->field_heading->position = $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'frame_position') ?? 'left';
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

    // If it's a wide section, display the summary.
    if ($wide = $paragraph->getBehaviorSetting($this->getPluginId(), 'wide')) {
      $summary[] = [
        'label' => 'Wide',
        'value' => '✓',
      ];
    }

    // If it's a wide section, display the summary.
    if ($wide = $paragraph->getBehaviorSetting($this->getPluginId(), 'gapless')) {
      $summary[] = [
        'label' => 'Gapless',
        'value' => '✓',
      ];
    }

    // Display the frame position.
    if ($position = $paragraph->getBehaviorSetting($this->getPluginId(), 'frame_position')) {
      $summary[] = [
        'label' => 'Frame',
        'value' => $position,
      ];
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * This behavior is only applicable to paragraphs that are of type 'section'.
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    return $paragraphs_type->id() === 'section';
  }

}
