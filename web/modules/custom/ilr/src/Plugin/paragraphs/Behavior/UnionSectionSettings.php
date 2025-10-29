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
    '' => 'None',
  ];

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    // There is no #parents key in $form, but this may be OK hardcoded.
    $parents = $form['#parents'];
    $parents_input_name = array_shift($parents);
    $parents_input_name .= '[' . implode('][', $parents) . ']';

    $form['wide'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Wide'),
      '#min' => 1,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'wide'),
    ];

    $form['heading_left'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Place heading on left'),
      '#min' => 1,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'heading_left'),
    ];

    $form['first_component_to_blurb'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Place the first component in the heading'),
      '#description' => $this->t('Use the first Rich text, Form, or Image component in the heading body/blurb area.'),
      '#min' => 1,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'first_component_to_blurb'),
    ];

    $form['frame_position'] = [
      '#type' => 'radios',
      '#title' => $this->t('Frame position'),
      '#options' => $this->position,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'frame_position') ?? '',
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
      $variables['attributes']['class'][] = 'cu-section--wide';
    }

    if ($variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'heading_left')) {
      $variables['attributes']['class'][] = 'cu-section--left';
    }

    if ($frame_position = $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'frame_position')) {
      $variables['attributes']['class'][] = 'cu-section--framed';
      $variables['attributes']['class'][] = 'cu-section--framed-' . $frame_position;
    }

    if ($variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'first_component_to_blurb')) {
      foreach ($variables['paragraph']->field_components->referencedEntities() as $id => $component_paragraph) {
        if (in_array($component_paragraph->bundle(), ['rich_text', 'form', 'image'])) {
          $variables['blurb'] = $variables['content']['field_components'][$id];
          $variables['content']['field_components'][$id]['#access'] = FALSE;
          break;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraphs_entity, EntityViewDisplayInterface $display, $view_mode) {
    if (!$paragraphs_entity->isPublished()) {
      $build = [];
    }
  }

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
