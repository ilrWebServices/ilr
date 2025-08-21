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
 *   id = "frame_media",
 *   label = @Translation("Frame media"),
 *   description = @Translation("Frame media elements in this component."),
 *   weight = 1
 * )
 */
class FrameMedia extends ParagraphsBehaviorBase {

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    // There is no #parents key in $form, but this may be OK hardcoded.
    $parents = $form['#parents'];
    $parents_input_name = array_shift($parents);
    $parents_input_name .= '[' . implode('][', $parents) . ']';

    $form['frame_media'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Frame media elements in this component'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'frame_media') ?? 0,
    ];

    $form['frame_color'] = [
      '#type' => 'select',
      '#title' => $this->t('Frame color'),
      '#options' => [
        'light' => 'Light',
        'dark' => 'Dark',
        'vibrant' => 'Cornell Red',
      ],
      '#states' => [
        'visible' => [
          ':input[name="' . $parents_input_name . '[frame_media]"]' => ['checked' => TRUE],
        ],
      ],
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'frame_color') ?? 'light',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $filtered_values = $this->filterBehaviorFormSubmitValues($paragraph, $form, $form_state);

    // Unset the events_shown limit when both the start and end date are specified.
    if (empty($filtered_values['frame_media'])) {
      unset($filtered_values['frame_color']);
    }

    $paragraph->setBehaviorSettings($this->getPluginId(), $filtered_values);
  }
  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    if ($color = $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'frame_color') ?? '') {
      $variables['attributes']['class'][] = 'cu-component--framed-media';
      $variables['attributes']['class'][] = 'cu-component--frame-' . $color;
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

    if ($paragraph->getBehaviorSetting($this->getPluginId(), 'frame_media')) {
      $summary[] = [
        'label' => 'Columns',
        'value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'columns'),
      ];
    }

    return $summary;
  }

}
