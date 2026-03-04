<?php

namespace Drupal\ilr\Plugin\paragraphs\Behavior;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
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
      $scheduled_status = $this->getScheduledStatus($paragraphs_entity);
      $timestamp = array_key_first($scheduled_status);

      if (reset($scheduled_status) === 'future') {
        $build = [
          '#type' => 'markup',
          '#markup' => Markup::create(sprintf('<!-- Scheduled visibility placeholder: pending for %d -->', $paragraphs_entity->id())),
          '#cache' => [
            'max-age' => $timestamp,
          ],
        ];
        return;
      }

      if (reset($scheduled_status) === 'past') {
        $build = [
          '#type' => 'markup',
          '#markup' => Markup::create(sprintf('<!-- Scheduled visibility placeholder: expired for %d -->', $paragraphs_entity->id())),
          '#cache' => [
            'max-age' => Cache::PERMANENT,
          ],
        ];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(Paragraph $paragraph) {
    $summary = [];

    if ($paragraph->getBehaviorSetting($this->getPluginId(), 'visiblity_scheduled')) {
      $scheduled_status = $this->getScheduledStatus($paragraph);

      switch (reset($scheduled_status)) {
        case 'present':
          $label = 'Visible';
          break;

        case 'past':
          $label = 'Hidden';
          break;

        case 'future':
          $label = 'Scheduled';
          break;
      }

      $summary[] = [
        'label' => $this->t($label),
        'value' => Markup::create('<span class="scheduled scheduled--'. reset($scheduled_status).'">⏰</span>'),
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
   * @return array
   *   An single array with a human-readable value and some date
   *   info (when relevant).
   */
  protected function getScheduledStatus($paragraph):array {
    $today = new DrupalDateTime();

    if ($show_on = $paragraph->getBehaviorSetting($this->getPluginId(), ['date_container', 'visiblity_scheduled_start'])) {
      if ($show_on > $today) {
        $seconds_remaining = $show_on->getTimestamp() - $today->getTimestamp();
        return [$seconds_remaining => 'future'];
      }
    }

    if ($hide_on = $paragraph->getBehaviorSetting($this->getPluginId(), ['date_container', 'visiblity_scheduled_end'])) {
      if ($hide_on < $today) {
        return [$hide_on->getTimestamp() => 'past'];
      }
    }

    return ['present'];
  }

}
