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
   * The icon options.
   *
   * @var array
   */
  protected $iconOptions = [
    'check-circle' => 'check-circle',
    'cornell-seal' => 'cornell-seal',
    'facebook' => 'facebook',
    'handshake' => 'handshake',
    'ilr-nickname' => 'ilr-nickname',
    'instagram' => 'instagram',
    'linkedin' => 'linkedin',
    'mortarboard' => 'mortarboard',
    'news-phone' => 'news-phone',
    'newsletter' => 'newsletter',
    'speech-bubble' => 'speech-bubble',
    'student' => 'student',
    'profile' => 'profile',
    'tower' => 'tower',
    'twitter' => 'twitter',
    'play' => 'play',
    'pause' => 'pause',
    'youtube' => 'youtube',
  ];

  /**
   * The icon placement options.
   *
   * @var array
   */
  protected $iconPlacementOptions = [
    'inline' => 'Inline',
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

    $form['icon'] = [
      '#type' => 'select',
      '#title' => $this->t('Icon'),
      '#description' => $this->t('An optional icon to add to the top of the content area in the card.'),
      '#options' => $this->iconOptions,
      '#required' => FALSE,
      '#empty_option' => $this->t('- Select -'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'icon') ?? '',
    ];

    $form['icon_label'] = [
      '#type' => 'textfield',
      '#title' => t('Icon label'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'icon_label'),
      '#maxlength' => '50',
      '#description' => t('Optional. This text will appear below or next to the icon.'),
      '#states' => [
        'invisible' => [
          ':input[name="' . $parents_input_name . '[icon]"]' => [
            ['value' => ''],
          ],
        ],
      ],
    ];

    $form['icon_placement'] = [
      '#type' => 'select',
      '#title' => t('Icon label placement'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'icon_placement') ?? '',
      '#options' => $this->iconPlacementOptions,
      '#empty_option' => $this->t('Centered (default)'),
      '#states' => [
        'invisible' => [
          ':input[name="' . $parents_input_name . '[icon]"]' => [
            ['value' => ''],
          ],
        ],
      ],
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

    if ($icon = $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'icon')) {
      $variables['paragraph']->cu_icon = [
        'title' => $icon,
        'icon' => $icon,
      ];

      if ($icon_placement = $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'icon_placement')) {
        $variables['paragraph']->cu_icon['attributes'] = ['class' => 'cu-icon--' . $icon_placement];
      }

      if ($label = $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'icon_label')) {
        $variables['paragraph']->cu_icon['label'] = $label;
        $variables['paragraph']->cu_icon['title'] = $label;
      }
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
