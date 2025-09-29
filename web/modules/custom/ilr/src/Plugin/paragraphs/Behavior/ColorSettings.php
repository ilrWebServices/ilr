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
 *   id = "ilr_color",
 *   label = @Translation("Color settings"),
 *   description = @Translation("Configure component color related settings (e.g. light, dark, vibrant, etc.)."),
 *   weight = 3
 * )
 */
class ColorSettings extends ParagraphsBehaviorBase {

  /**
   * The color schemes.
   *
   * @var array
   */
  protected $colorSchemes = [
    'none' => 'None',
    'light' => 'Light',
    'dark' => 'Dark',
    'vibrant' => 'Cornell Red',
  ];

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();

    $form['color_schemes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed color schemes'),
      '#options' => $this->colorSchemes,
      '#default_value' => $config['color_schemes'] ?? array_keys($this->colorSchemes),
    ];

    $form['color_scheme_default'] = [
      '#type' => 'select',
      '#title' => $this->t('Default color scheme'),
      '#options' => $this->colorSchemes,
      '#default_value' => $config['color_scheme_default'] ?? 'none',
    ];

    $form['allow_framed_media'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow media elements in this component to be framed.'),
      '#default_value' => $config['allow_framed_media'] ?? 0,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $enabled_color_schemes = array_filter($form_state->getValue('color_schemes'));

    if (!empty($enabled_color_schemes) && $chosen_default = $form_state->getValue('color_scheme_default')) {
      if (!in_array($chosen_default, $enabled_color_schemes)) {
        $form_state->setError($form['color_scheme_default'], $this->t('The selected default color scheme is not in the allowed options.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['color_schemes'] = $form_state->getValue('color_schemes');
    $this->configuration['color_scheme_default'] = $form_state->getValue('color_scheme_default');
    $this->configuration['allow_framed_media'] = $form_state->getValue('allow_framed_media');
  }

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();

    $color_scheme_options = array_filter($this->colorSchemes, function($k) use ($config) {
      return (bool) $config['color_schemes'][$k];
    }, ARRAY_FILTER_USE_KEY);

    if (!empty($color_scheme_options)) {
      $form['color_scheme'] = [
        '#type' => 'select',
        '#title' => $this->t('Color scheme'),
        '#description' => $this->t('The color style for this component.'),
        '#options' => $color_scheme_options,
        '#required' => FALSE,
        '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'color_scheme') ?? $this->configuration['color_scheme_default'],
      ];
    }

    if (!empty($config['allow_framed_media'])) {
      $form['frame_media'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Frame media elements in this component'),
        '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'frame_media') ?? 0,
      ];

      // There is no #parents key in $form, but this may be OK hardcoded.
      $parents = $form['#parents'];
      $parents_input_name = array_shift($parents);
      $parents_input_name .= '[' . implode('][', $parents) . ']';

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

    }

    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    $color_scheme = $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'color_scheme');

    if (!empty($color_scheme) && $color_scheme !== 'none') {
      $variables['attributes']['class'][] = 'cu-colorscheme--' . $color_scheme;
    }

    if ($variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'frame_media')) {
      $variables['attributes']['class'][] = 'cu-component__frame';
      $variables['attributes']['class'][] = 'cu-component__frame--' . $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'frame_color');
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

    if ($color_scheme = $paragraph->getBehaviorSetting($this->getPluginId(), 'color_scheme')) {
      $summary[] = [
        'label' => $this->t('Color scheme'),
        'value' => $this->colorSchemes[$color_scheme],
      ];
    }

    return $summary;
  }

}
