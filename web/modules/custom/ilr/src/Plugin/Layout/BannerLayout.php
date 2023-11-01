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
  }

}
