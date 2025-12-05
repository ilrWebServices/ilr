<?php

namespace Drupal\ilr\Plugin\Layout;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Layout\LayoutDefault;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Configurable Banner layout.
 */
class BannerLayout extends LayoutDefault implements PluginFormInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'color_scheme' => 'dark',
      'hide_frame' => FALSE,
      'text_align' => 'left',
      'full_bleed' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $configuration = $this->getConfiguration();

    $form['color_scheme'] = [
      '#type' => 'select',
      '#title' => $this->t('Color scheme'),
      '#options' => [
        'none' => 'None',
        'light' => 'Light',
        'dark' => 'Dark',
        'vibrant' => 'Cornell Red',
      ],
      '#required' => FALSE,
      '#default_value' => $configuration['color_scheme'],
    ];

    $form['hide_frame'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide frame'),
      '#description' => $this->t('Remove the frame/border around the banner.'),
      '#default_value' => $configuration['hide_frame'],
    ];

    $form['text_align'] = [
      '#type' => 'select',
      '#title' => $this->t('Text alignment'),
      '#options' => [
        'left' => 'Left',
        'center' => 'Center',
      ],
      '#default_value' => $configuration['text_align'],
    ];

    $form['full_bleed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Full bleed'),
      '#description' => $this->t('Extend the banner to the full width of the viewport.'),
      '#default_value' => $configuration['full_bleed'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // This abstract method required by PluginFormInterface.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['color_scheme'] = $form_state->getValue('color_scheme');
    $this->configuration['hide_frame'] = (bool) $form_state->getValue('hide_frame');
    $this->configuration['text_align'] = $form_state->getValue('text_align');
    $this->configuration['full_bleed'] = (bool) $form_state->getValue('full_bleed');
  }

}
