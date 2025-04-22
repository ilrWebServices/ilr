<?php

namespace Drupal\ilr\Plugin\paragraphs\Behavior;

use Drupal\paragraphs\ParagraphsBehaviorBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;

/**
 * Provides a Card paragraph behavior plugin.
 *
 * @ParagraphsBehavior(
 *   id = "ilr_icon",
 *   label = @Translation("Icon settings"),
 *   description = @Translation("Configure icon settings for components that support them."),
 *   weight = 3
 * )
 */
class IconSettings extends ParagraphsBehaviorBase {

  /**
   * The icon placement options.
   *
   * @var array
   */
  protected $iconPlacementOptions = [
    'inline' => 'Inline',
    'inline-centered' => 'Inline-centered',
  ];

  /**
   * The icon options.
   *
   * @var array
   */
  protected $iconOptions = [
    'check-circle' => 'check-circle',
    'cornell-seal' => 'cornell-seal',
    'envelope' => 'envelope',
    'facebook' => 'facebook',
    'handshake' => 'handshake',
    'ilr-nickname' => 'ilr-nickname',
    'instagram' => 'instagram',
    'laptop' => 'laptop',
    'linkedin' => 'linkedin',
    'mortarboard' => 'mortarboard',
    'news-phone' => 'news-phone',
    'newsletter' => 'newsletter',
    'speech-bubble' => 'speech-bubble',
    'student' => 'student',
    'phone' => 'phone',
    'profile' => 'profile',
    'tower' => 'tower',
    'twitter' => 'twitter',
    'play' => 'play',
    'pause' => 'pause',
    'youtube' => 'youtube',
  ];

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    // There is no #parents key in $form, but this may be OK hardcoded.
    $parents = $form['#parents'];
    $parents_input_name = array_shift($parents);
    $parents_input_name .= '[' . implode('][', $parents) . ']';

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
      '#title' => t('Icon placement'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'icon_placement') ?? '',
      '#options' => $this->iconPlacementOptions,
      '#empty_option' => $this->t('Centered (default)'),
      // '#description' => t('Optional. This text will appear below or next to the icon.'),
      '#states' => [
        'invisible' => [
          ':input[name="' . $parents_input_name . '[icon]"]' => [
            ['value' => ''],
          ],
        ],
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    if ($icon = $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'icon')) {
      $cu_icon = [
        'title' => $icon,
        'icon' => $icon,
      ];

      if ($icon_placement = $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'icon_placement')) {
        $cu_icon['attributes'] = ['class' => 'cu-icon--' . $icon_placement];
      }

      if ($label = $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'icon_label')) {
        $cu_icon['label'] = $label;
        $cu_icon['title'] = $label;
      }

      // Deprecated.
      $variables['paragraph']->cu_icon = $cu_icon;

      // Future versions of PHP won't allow dynamic properties like `cu_icon`
      // above. So in addition, add it as a theme variable.
      $variables['cu_icon'] = $cu_icon;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraphs_entity, EntityViewDisplayInterface $display, $view_mode) {}

}
