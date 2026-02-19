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
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'visiblity_scheduled') ?? FALSE,
    ];

    $form['visiblity_scheduled_start'] = [
      '#type' => 'date',
      '#title' => $this->t('Show on:'),
      '#description' => $this->t('Leave blank to show immediately.<br><br>'),
      '#states' => [
        'visible' => [
          ':input[name="' . $parents_input_name . '[visiblity_scheduled]"]' => ['checked' => TRUE],
        ],
      ],
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'visiblity_scheduled_start') ?? '',
    ];

    $form['visiblity_scheduled_end'] = [
      '#type' => 'date',
      '#title' => $this->t('Hide on:'),
      '#description' => $this->t('Leave blank to show indefinitely.'),
      '#states' => [
        'visible' => [
          ':input[name="' . $parents_input_name . '[visiblity_scheduled]"]' => ['checked' => TRUE],
        ],
      ],
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'visiblity_scheduled_end') ?? [],

    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraphs_entity, EntityViewDisplayInterface $display, $view_mode) {
    if ($paragraphs_entity->getBehaviorSetting($this->getPluginId(), 'visiblity_scheduled')) {
      $today = new DrupalDateTime();

      if ($show_on = $paragraphs_entity->getBehaviorSetting($this->getPluginId(), 'visiblity_scheduled_start')) {
        if ($show_on > $today) {
          $seconds_remaining = $show_on->getTimestamp() - $today->getTimestamp();

          $build = [
            '#type' => 'markup',
            '#markup' => Markup::create(sprintf('<!-- Scheduled visibility placeholder: pending for %d -->', $paragraphs_entity->id())),
            '#cache' => [
              'max-age' => $seconds_remaining,
            ],
          ];

          return;
        }
      }

      if ($hide_on = $paragraphs_entity->getBehaviorSetting($this->getPluginId(), 'visiblity_scheduled_end')) {
        if ($hide_on < $today) {
          $build = [
            '#type' => 'markup',
            '#markup' => Markup::create(sprintf('<!-- Scheduled visibility placeholder: expired for %d -->', $paragraphs_entity->id())),
            '#cache' => [
              'max-age' => Cache::PERMANENT,
            ],
          ];

          return;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(Paragraph $paragraph) {
    $summary = [];

    if ($paragraph->getBehaviorSetting($this->getPluginId(), 'visiblity_scheduled')) {
      $summary[] = [
        'label' => $this->t('Scheduled'),
        'value' => '⏰',
      ];
    }

    return $summary;
  }

}
