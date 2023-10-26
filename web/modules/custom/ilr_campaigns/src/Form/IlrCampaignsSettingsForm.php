<?php

namespace Drupal\ilr_campaigns\Form;

use Drupal\Core\Form\ConfigFormBase;
use CampaignMonitor\CampaignMonitorRestClient;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements a settings form for ILR Campaigns.
 */
class IlrCampaignsSettingsForm extends ConfigFormBase {

  /**
   * The Campaign Montitor REST Client.
   *
   * @var \CampaignMonitor\CampaignMonitorRestClient
   */
  protected $client;

  /**
   * Constructs a settings form object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \CampaignMonitor\CampaignMonitorRestClient $campaign_monitor_rest_client
   *   The rest client.
   */
  public function __construct(ConfigFactoryInterface $config_factory, CampaignMonitorRestClient $campaign_monitor_rest_client) {
    parent::__construct($config_factory);
    $this->client = $campaign_monitor_rest_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('campaign_monitor_rest_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ilr_campaigns_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'ilr_campaigns.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('ilr_campaigns.settings');
    $client_id = $config->get('course_notification_client_id');
    $client_options = ['' => '-- Select Client --'];

    // Build the options to select options.
    try {
      $response = $this->client->get('clients.json');
      foreach ($response->getData() as $client) {
        $client_options[$client['ClientID']] = $client['Name'];
      }

      $form['course_notification_client_id'] = [
        '#type' => 'select',
        '#title' => $this->t('Client'),
        '#options' => $client_options,
        '#default_value' => $client_id,
        '#required' => TRUE,
      ];
    }
    catch (\Exception $e) {
      return parent::buildForm($form, $form_state);
    }

    if ($client_id) {
      $options = ['' => $this->t('-- Select --')];

      // Call the CM API to output the lists as options.
      try {
        $response = $this->client->get("clients/$client_id/lists.json");

        foreach ($response->getData() as $list) {
          $options[$list['ListID']] = $list['Name'];
        }

        if (count($options) > 1) {
          $form['course_notification_list_id'] = [
            '#type' => 'select',
            '#title' => $this->t('List'),
            '#description' => $this->t('Select the list to use for Course Notifications.'),
            '#default_value' => $config->get('course_notification_list_id'),
            '#options' => $options,
          ];
        }
        else {
          $form['course_notification_list_notice'] = [
            '#markup' => $this->t('No lists found in Campaign Monitor for this client.'),
            '#prefix' => '<p>',
            '#suffix' => '</p>',
          ];
        }
      }
      catch (\Exception $e) {
        // Fail silently.
      }
    }

    $form['campaign_preview'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Preview campaigns'),
      // '#description' => $this->t('.'),
      '#default_value' => $config->get('campaign_preview'),
    ];

    $form['campaign_send'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send campaigns'),
      // '#description' => $this->t('.'),
      '#default_value' => $config->get('campaign_send'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('ilr_campaigns.settings');
    $config->set('course_notification_client_id', $form_state->getValue('course_notification_client_id'));
    $config->set('course_notification_list_id', $form_state->getValue('course_notification_list_id'));
    $config->set('campaign_preview', $form_state->getValue('campaign_preview'));
    $config->set('campaign_send', $form_state->getValue('campaign_send'));
    $config->save();
    $this->messenger()->addMessage($this->t('Configuration saved.'));
  }

}
