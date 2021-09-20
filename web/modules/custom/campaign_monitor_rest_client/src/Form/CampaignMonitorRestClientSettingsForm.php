<?php

namespace Drupal\campaign_monitor_rest_client\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements a Campaign Monitor Rest Client Settings Config Form.
 */
class CampaignMonitorRestClientSettingsForm extends ConfigFormBase {

  /**
   * @return string
   */
  public function getFormId() {
    return 'campaign_monitor_rest_client_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'campaign_monitor_rest_client.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('campaign_monitor_rest_client.settings');

    $form['api_key'] = [
      '#type' => 'textarea',
      '#title' => $this->t('API Key'),
      '#description' => $this->t('The API key used to access the Campaign Monitor REST API.'),
      '#default_value' => $config->get('api_key'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('campaign_monitor_rest_client.settings');
    $config->set('api_key', $form_state->getValue('api_key'))->save();
  }

}
