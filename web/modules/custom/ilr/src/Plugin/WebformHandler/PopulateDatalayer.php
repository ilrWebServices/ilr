<?php

namespace Drupal\ilr\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ilr_analytics_session\IlrAnalyticsSessionManager;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Datalayer webform handler.
 *
 * @WebformHandler(
 *   id = "populate_datalayer",
 *   label = @Translation("Populate Datalayer"),
 *   description = @Translation("Used to add webform submission data to the datalayer."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 * )
 */
class PopulateDatalayer extends WebformHandlerBase {

  /**
   * Event data with replaced tokens.
   *
   * @var string|array<mixed>
   */
  private $eventData;

  /**
   * ILRAnalyticsSessionManager
   *
   * @var IlrAnalyticsSessionManager
   */
  protected $analyticsSessionManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->analyticsSessionManager = $container->get('ilr_analytics_session_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'datalayer' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['config'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Datalayer'),
    ];
    $form['config']['datalayer'] = [
      '#type' => 'webform_codemirror',
      '#mode' => 'yaml',
      '#title' => $this->t('Settings'),
      '#description' => $this->t("Datalayer properties include `type` (e.g. 'lead', 'subscribe', 'other') and `elements`."),
      '#default_value' => $this->configuration['datalayer'],
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
  public function alterForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    if (empty($this->eventData)) {
      /** @var \Drupal\Webform\WebformSubmissionStorageInterface $submission_storage */
      $submission_storage = $this->entityTypeManager->getStorage('webform_submission');
      /** @var \Drupal\Webform\WebformSubmissionInterface $last_submission */
      $last_submission = $submission_storage->getLastSubmission($webform_submission->getWebform(), $webform_submission->getSourceEntity(), NULL, ['in_draft' => FALSE, 'access_check' => FALSE]);

      // Since we're skipping the access_check for anonymous users, we need to
      // compare the analytics session ids to ensure we have the appropriate
      // submission.
      $submission_client_id = $last_submission->getElementData('ga_client_id');
      $current_client_id = $this->analyticsSessionManager->getClientId();

      // Ensure there is a previous submission and it was completed in the last 5 seconds.
      if (empty($last_submission) || (time() - $last_submission->getCompletedTime() > 5) || $submission_client_id !== $current_client_id) {
        return;
      }
      $this->setEventData($last_submission);
    }

    $form['#attached']['library'][] = 'union_marketing/interaction-analytics';
    $form['#attached']['drupalSettings']['ilr_webform_data'] = $this->eventData;
    $form['#attached']['drupalSettings']['ilr_include_ajax'] = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $this->setEventData($webform_submission);
  }

  /**
   * Set the event data so it can be added to the dataLayer.
   *
   * @param WebformSubmissionInterface $webform_submission
   * @return void
   */
  protected function setEventData(WebformSubmissionInterface $webform_submission) {
    $webform = $webform_submission->getWebform();

    // Check for a confirmation message, and replace any existing tokens if found.
    if ($confirmation_message = $webform->getSetting('confirmation_message', '')) {
      $message = $this->replaceTokens($confirmation_message, $webform_submission);
    }

    $data = [
      'id' => $webform->id(),
      'name' => $webform->label(),
      'uuid' => $webform_submission->uuid() ?? 'unknown',
      'data' => [],
      'type' => $this->configuration['datalayer']['type'] ?? 'lead',
      'done' => 'true',
      'url' => ($webform_submission->getSourceUrl()) ? $webform_submission->getSourceUrl()->toString() : 'unknown',
      'result' => [
        'count' => 1,
        'message' => $message ?? 'unknown',
      ]
    ];

    if (isset($this->configuration['datalayer']['elements'])) {
      foreach ($this->configuration['datalayer']['elements'] as $element) {
        if (is_array($element)) {
          $values = $webform_submission->getelementData(array_key_first($element));

          if (empty($values)) {
            continue;
          }

          foreach ($element as $subelements) {
            foreach ($subelements as $subelement) {
              if (array_key_exists($subelement, $values)) {
                $data['data'][$subelement] = $values[$subelement];
              }
            }

          }
        }
        elseif ($value = $webform_submission->getelementData($element)) {
          $data['data'][$element] = $value;
        }
      }
    }

    $this->eventData = [
      'event' => 'page.submit',
      'page.submit' => $data,
    ];
  }
}
