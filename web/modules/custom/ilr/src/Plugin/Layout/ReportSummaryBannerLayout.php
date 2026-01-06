<?php

namespace Drupal\ilr\Plugin\Layout;

use Drupal\Core\Layout\LayoutDefault;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a Report Summary Banner layout.
 *
 * @Layout(
 *   id = "report_summary_banner",
 *   label = @Translation("Report Summary Banner"),
 *   category = @Translation("ILR"),
 *   theme_hook = "report_summary_banner",
 *   regions = {
 *     "banner_main" = {
 *       "label" = @Translation("Banner Main")
 *     },
 *     "banner_image" = {
 *       "label" = @Translation("Banner Image")
 *     },
 *     "banner_sidebar" = {
 *       "label" = @Translation("Banner Sidebar")
 *     }
 *   }
 * )
 */
class ReportSummaryBannerLayout extends LayoutDefault implements PluginFormInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'background_image' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $configuration = $this->getConfiguration();
    
    $form['background_image'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Background Image URL'),
      '#default_value' => $configuration['background_image'],
      '#description' => $this->t('Optional background image URL for the banner.'),
    ];
    
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['background_image'] = $form_state->getValue('background_image');
  }

}
