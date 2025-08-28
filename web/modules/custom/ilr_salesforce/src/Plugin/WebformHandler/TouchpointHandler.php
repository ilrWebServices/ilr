<?php

namespace Drupal\ilr_salesforce\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
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
   * A key-value store for Touchpoint SFIDs.
   */
  protected KeyValueStoreInterface $sfDataStore;

  /**
   * The logger.
   */
  protected LoggerInterface $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->sfapi = $container->get('salesforce.client');
    $instance->sfDataStore = $container->get('keyvalue')->get('ilr_salesforce.touchpoint.sfid');
    $instance->logger = $container->get('logger.factory')->get('webform_touchpoint');
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
      '#description' => $this->t('Define extra values (AKA constants) to map. One value per line. Only after saving this handler, values will be available in the mappings-field to map with a SalesForce-field. To use webform_submission tokens, use the format token(webform_submission:url)'),
    ];

    $form['details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Fields mapping'),
    ];

    $form['details']['fields_mapping'] = [
      '#type' => 'webform_mapping',
      '#title' => $this->t('Fields mapping'),
      '#title_display' => 'invisible',
      '#webform_id' => $webform->id(),
      '#required' => FALSE,
      '#description' => $this->t('Please map webform fields to Salesforce Touchpoint fields.'),
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
  public function postLoad(WebformSubmissionInterface $webform_submission) {
    $notes = $webform_submission->getNotes() ?? '';

    // Add the touchpoint id for this submission to the notes. This is for
    // temporary display only. It will be removed in self::preSave().
    if ($touchpoint_sfid = $this->sfDataStore->get($webform_submission->id())) {
      $webform_submission->setNotes($notes . $this->formatMapNote($touchpoint_sfid));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(WebformSubmissionInterface $webform_submission) {
    $notes = $webform_submission->getNotes() ?? '';

    // Remove the touchpoint id from the submission notes to prevent duplicate
    // values. This value was added in self::postLoad() for informational use.
    // https://regex101.com/r/bdoJLa/1
    if (preg_match_all('/\s*{ Touchpoint: \w+ }/m', $notes, $matches, PREG_SET_ORDER, 0)) {
      $webform_submission->setNotes(preg_replace('/\s*{ Touchpoint: \w+ }/m', '', $notes));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    // Check the key-value store to see if we've sent this submission.
    if ($this->sfDataStore->get($webform_submission->id())) {
      return;
    }

    // TODO: Figure out why the data is slightly different when submitting via the Edit and Notes forms. In our case, the `Texting_Opt_In__c` field is sometimes a string and sometimes an int.
    $data = $webform_submission->getData();
    $touchpoint_vars = $this->createMergeVars($data);


    if (isset($data['opt_in']) && $data['opt_in'] === 0) {
      // Respect "opt in" status if asked on information requests.
      if (isset($touchpoint_vars['Source__c']) && strtolower($touchpoint_vars['Source__c']) === 'information request') {
        return;
      }
      // Unset any emps that may have been set.
      unset($touchpoint_vars['Touchpoint_EMPS__c']);
    }

    // Check for company_account and company values.
    // Set a default if company was omitted.
    if (!empty($data['company_account'])) {
      $touchpoint_vars['Account_ID__c'] = $data['company_account'];
    }
    elseif (!empty($data['company'])) {
      $touchpoint_vars['Company__c'] = $data['company'];
    }
    else {
      $touchpoint_vars['Company__c'] = 'NONE_PROVIDED';
    }

    // Add any custom questions/answers.
    $webform = $webform_submission->getWebform();
    $custom_1_element = $webform->getElement('custom_1_answer');
    $custom_2_element = $webform->getElement('custom_2_answer');

    if ($custom_1_element && $custom_1_element['#access'] && isset($data['custom_1_answer'])) {
      $custom_1_question = $custom_1_element['#title'] ?? 'Custom question 1';
      $custom_1_answer = is_array($data['custom_1_answer']) ? implode(';', $data['custom_1_answer']) : $data['custom_1_answer'];
      $touchpoint_vars['Custom1_Question__c'] = substr($custom_1_question, 0, 255);
      $touchpoint_vars['Custom1_Answer__c'] = substr($custom_1_answer, 0, 255);
    }

    if ($custom_2_element && $custom_2_element['#access'] && isset($data['custom_2_answer'])) {
      $custom_2_question = $custom_2_element['#title'] ?? 'Custom question 2';
      $custom_2_answer = is_array($data['custom_2_answer']) ? implode(';', $data['custom_2_answer']) : $data['custom_2_answer'];
      $touchpoint_vars['Custom2_Question__c'] = substr($custom_2_question, 0, 255);
      $touchpoint_vars['Custom2_Answer__c'] = substr($custom_2_answer, 0, 255);
    }

    // Add the URL to the page the form was submitted to.
    $touchpoint_vars['Origin__c'] = $webform_submission->getSourceUrl()->toString();

    // Semicolon delimit any array values to conform to the API expectations.
    foreach ($touchpoint_vars as $key => $value) {
      if (is_array(($value))) {
        $touchpoint_vars[$key] = implode(';', $value);
      }
    }

    try {
      $sf_results = $this->sfapi->apiCall('sobjects/Touchpoint__c', $touchpoint_vars, 'POST');

      // Save to store the notes.
      $this->sfDataStore->set($webform_submission->id(), $sf_results['id']);

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
      // Composite element.
      elseif (strpos($submission_key, ':') !== FALSE && strpos($submission_key, 'token(') === FALSE) {
        list($submission_key_1, $submission_key_2) = explode(':', $submission_key);
        $merge_vars[$destination_key] = $values[$submission_key_1][$submission_key_2] ?? NUll;
      }
      // Add extra mapping values.
      elseif (in_array($submission_key, preg_split('/\r\n|\r|\n/', $extra_values))) {
        if (strpos($submission_key, 'token(') !== FALSE) {
          $tokenified_submission_key = preg_replace('/token\((.*)\)/mU', '[$1]', $submission_key);
          $token_manager = \Drupal::service('webform.token_manager');
          $webform_submission = $this->getWebformSubmission();
          $token_data['webform_submission'] = $webform_submission;
          $merge_vars[$destination_key] = $token_manager->replace($tokenified_submission_key, $webform_submission, $token_data);
        }
        else {
          $merge_vars[$destination_key] = $submission_key;
        }
      }
    }

    return $merge_vars;
  }

  /**
   * Format a string of the submission to Touchpoint SFID.
   *
   * @param string $sfid
   *
   * @return string A formatted string for inclusion in a submission note.
   */
  private function formatMapNote(string $sfid): string {
    return sprintf("\n{ Touchpoint: %s }", trim($sfid));
  }

}
