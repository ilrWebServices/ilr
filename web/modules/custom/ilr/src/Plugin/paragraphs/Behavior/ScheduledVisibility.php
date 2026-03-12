<?php

namespace Drupal\ilr\Plugin\paragraphs\Behavior;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\ilr\ScheduleBehaviorInfo;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;

/**
 * Provides settings for the Card Overlay component.
 *
 * @ParagraphsBehavior(
 *   id = "scheduled_visibility",
 *   label = @Translation("Visibility scheduling"),
 *   description = @Translation("Configure visibility settings for this component."),
 *   weight = 1
 * )
 */
class ScheduledVisibility extends ParagraphsBehaviorBase {

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $parents = $form['#parents'];
    $parents_input_name = array_shift($parents);
    $parents_input_name .= '[' . implode('][', $parents) . ']';

    $form['visiblity_scheduled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Schedule visibility for this component.'),
      '#description' => $this->t('Selecting this setting will modify the UI. Green indicates current visibility, red elements are no longer visible, while yellow are not yet visible.'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'visiblity_scheduled') ?? FALSE,
    ];

    $form['date_container'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="' . $parents_input_name . '[visiblity_scheduled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Attach custom javascript to set a default time value.
    // @see ilr.datetime.enhancements.js.
    $form['date_container']['#attached']['library'][] = 'ilr/ilr_datetime_enhancements';

    $form['date_container']['visiblity_scheduled_start'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Show on:'),
      '#description' => $this->t('Leave blank to show immediately.<br><br>'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), ['date_container', 'visiblity_scheduled_start']) ?? [],
    ];

    $form['date_container']['visiblity_scheduled_end'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Hide on:'),
      '#description' => $this->t('Leave blank to show indefinitely.'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), ['date_container', 'visiblity_scheduled_end']) ?? [],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraphs_entity, EntityViewDisplayInterface $display, $view_mode) {
    if (\Drupal::routeMatch()->getRouteName() === 'paragraphs_previewer.form_preview') {
      return;
    }

    if ($paragraphs_entity->getBehaviorSetting($this->getPluginId(), 'visiblity_scheduled')) {
      $scheduled_info = $this->getScheduledInfo($paragraphs_entity);

      if ($scheduled_info->status === FALSE) {
        // Overwrite the build to replace the paragraph.
        $build = [
          '#type' => 'markup',
          '#markup' => Markup::create(sprintf('<!-- Scheduled visibility placeholder: %s for %d -->',
            $scheduled_info->reason === ScheduleBehaviorInfo::FUTURE ? 'pending' : 'expired',
            $paragraphs_entity->id()
          )),
        ];
      }

      if ($scheduled_info->secondsTillShowOrHide) {
        $build['#cache']['max-age'] = $scheduled_info->secondsTillShowOrHide;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(Paragraph $paragraph) {
    $summary = [];

    if ($paragraph->getBehaviorSetting($this->getPluginId(), 'visiblity_scheduled')) {
      $scheduled_info = $this->getScheduledInfo($paragraph);

      switch ($scheduled_info->reason) {
        case ScheduleBehaviorInfo::PRESENT:
          $label = 'Visible';
          $class_modifier = 'present';
          break;

        case ScheduleBehaviorInfo::PAST:
          $label = 'Expired';
          $class_modifier = 'past';
          break;

        case ScheduleBehaviorInfo::FUTURE:
          $label = 'Scheduled';
          $class_modifier = 'future';
          break;
      }

      $summary[] = [
        'label' => Markup::create('<span class="scheduled scheduled--' . $class_modifier . '">⏰</span>'),
        'value' => $this->t($label),
      ];
    }

    return $summary;
  }

  /**
   * Get the current status of the scheduled visibility.
   *
   * @param Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraphs entity.
   *
   * @return \Drupal\ilr\ScheduleBehaviorInfo
   */
  protected function getScheduledInfo($paragraph): ScheduleBehaviorInfo {
    return new ScheduleBehaviorInfo(
      $paragraph->getBehaviorSetting($this->getPluginId(), ['date_container', 'visiblity_scheduled_start']),
      $paragraph->getBehaviorSetting($this->getPluginId(), ['date_container', 'visiblity_scheduled_end'])
    );
  }

}
