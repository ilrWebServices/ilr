<?php

namespace Drupal\ilr_salesforce\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ilr_analytics_session\IlrAnalyticsSessionManager;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Webform handler to submit registration submissions to Salesforce webhook.
 *
 * @WebformHandler(
 *   id = "outreach_touchpoint_submitter",
 *   label = @Translation("ILR Outreach touchpoint submitter"),
 *   description = @Translation("Submits webform submissions to Salesforce Touchpoints."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_IGNORED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 * )
 */
class TouchpointHandler extends WebformHandlerBase {

  /**
   * The salesforce rest client.
   *
   * @var \Drupal\salesforce\Rest\RestClientInterface
   */
  protected $sfapi;

  /**
   * The logger.
   */
  protected LoggerInterface $logger;

  protected IlrAnalyticsSessionManager $analyticsSessionManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->sfapi = $container->get('salesforce.client');
    $instance->logger = $container->get('logger.factory')->get('webform_touchpoint');
    $instance->analyticsSessionManager = $container->get('ilr_analytics_session_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'extra_values' => '',
      'fields_mapping' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = $this->getWebform();

    $this->applyFormStateToConfiguration($form_state);
    $touchpoint_fields = [];

    try {
      $object_description = $this->sfapi->objectDescribe('Touchpoint__c');

      if (!is_object($object_description)) {
        throw new \Exception('Could not load info for Touchpoint__c.');
      }

      foreach ($object_description->getFields() as $field => $data) {
        $touchpoint_fields[$field] = sprintf('%s (%s)', $data['label'], $data['name']);
      }

      asort($touchpoint_fields);
    }
    catch (\Exception $e) {
      $this->logger->error('Touchpoint handler error: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    $form['extra_values'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Extra values'),
      '#default_value' => $this->configuration['extra_values'],
      '#description' => $this->t('Define extra values to map. One value per line. Only after saving this handler, values will be available in the mappings-field to map with a SalesForce-field.'),
    ];

    $form['fields_mapping'] = [
      '#type' => 'webform_mapping',
      '#title' => $this->t('Fields mapping'),
      '#title_display' => 'invisible',
      '#webform_id' => $webform->id(),
      '#required' => FALSE,
      '#description' => $this->t('Please map webform fields to Salesforce Touchpoint fields. Use a pipe to map to multiple fields.'),
      '#description_display' => 'before',
      '#default_value' => $this->configuration['fields_mapping'],
      '#source' => $this->getWebformMappingOptions(),
      '#destination__type' => 'select',
      '#destination' => $touchpoint_fields,
    ];

    return $this->setSettingsParents($form);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->applyFormStateToConfiguration($form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function preCreate(array &$values) {
    // Set the ga_client_id here instead of using a token in the default value
    // for the hidden field. We do this because the default value is also used
    // when viewing submissions that don't have a value for this element.
    // TODO: Consider doing this in a separate handler, since some forms submit via salesforce_mappings.
    if ($this->getWebform()->getElement('ga_client_id')) {
      $values['data']['ga_client_id'] = $this->analyticsSessionManager->getClientId();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    $notes = $webform_submission->getNotes();

    // Check the notes to see if we've sent this submission.
    if (strpos($notes, 'Touchpoint:') !== FALSE) {
      return;
    }

    $data = $webform_submission->getData();
    $touchpoint_vars = $this->createMergeVars($data);

    // Add the URL to the page the form was submitted to.
    $touchpoint_vars['Origin__c'] = $webform_submission->getSourceUrl()->toString();

    try {
      $sf_results = $this->sfapi->apiCall('sobjects/Touchpoint__c', $touchpoint_vars, 'POST');
      $webform_submission->setNotes($notes . ' Touchpoint: ' . $sf_results['id']);

      // Save to store the notes.
      $webform_submission->save();

      $this->logger->info('Touchpoint %id created for submission @webform_submission.', [
        '@webform_submission' => $webform_submission->id(),
        '%id' => $sf_results['id'],
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Touchpoint handler error for webform submission @webform_submission: @message', [
        '@webform_submission' => $webform_submission->id(),
        '@message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getWebformMappingOptions() {
    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = $this->getWebform();
    $options = [];
    $elements = $webform->getElementsInitializedFlattenedAndHasValue('view');

    // Replace tokens which can be used in an element's #title.
    /** @var \Drupal\webform\WebformTokenManagerInterface $token_manager */
    $token_manager = \Drupal::service('webform.token_manager');
    $elements = $token_manager->replace($elements, $webform);

    foreach ($elements as $key => $element) {
      $title = $element['#admin_title'] ?: $element['#title'] ?: $key;

      if (!$element['#webform_composite']) {
        $options[$key] = $title . ' [' . $key . ']';
      }
      else {
        foreach ($element['#webform_composite_elements'] as $composite_element_key => $composite_element) {
          $composite_element_title = $title . ':' . $composite_element['#title'] ?: $key . ':' . $composite_element_key;
          $options[$key . ':' . $composite_element_key] = $composite_element_title . ' [' . $key . ':' . $composite_element_key . ']';
        }
      }
    }

    // Add extra custom values mapping.
    $extra_values = $this->configuration['extra_values'];
    if (isset($extra_values) && !empty($extra_values)) {
      // Split by newline.
      $values = preg_split('/\r\n|\r|\n/', $extra_values);

      foreach($values as $value) {
        $options[$value] = $this->t('Value:') . ' ' . $value;
      }
    }

    return $options;
  }

  /**
   * Build the Merge Vars array.
   *
   * @param $values
   */
  protected function createMergeVars($values) {
    $merge_vars = [];
    $field_mapping = $this->configuration['fields_mapping'];
    $extra_values = $this->configuration['extra_values'];

    foreach ($field_mapping as $submission_key => $destination_key) {
      if (isset($values[$submission_key]) && $values[$submission_key] != '') {
        // Multiple destinations can be set with a pipe.
        $destination_keys = explode('|', $destination_key);

        foreach ($destination_keys as $destination_key) {
          $merge_vars[$destination_key] = $values[$submission_key];
        }
      }
      elseif (strpos($submission_key, ':') !== FALSE) {
        // Composite element.
        list($submission_key_1, $submission_key_2) = explode(':', $submission_key);
        $merge_vars[$destination_key] = $values[$submission_key_1][$submission_key_2] ?? NUll;
      }
      elseif (in_array($submission_key, preg_split('/\r\n|\r|\n/', $extra_values))) {
        // Add extra mapping values.
        $merge_vars[$destination_key] = $submission_key;
      }
    }

    return $merge_vars;
  }

}
