<?php

namespace Drupal\webform_email_confirm\Plugin\WebformHandler;

use Drupal\Component\Utility\Crypt;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Webform handler to submit registration submissions to Salesforce webhook.
 *
 * @WebformHandler(
 *   id = "email_confirm",
 *   label = @Translation("Email confirm"),
 *   description = @Translation("Sends a message to configured email elements to confirm the address with a validation link."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_IGNORED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 * )
 */
class EmailConfirmationWebformHandler extends WebformHandlerBase {

  /**
   * A mail manager for sending email.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The shared tempstore.
   *
   * @var \Drupal\Core\TempStore\SharedTempStore
   */
  protected $tempstore;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->mailManager = $container->get('plugin.manager.mail');
    $instance->logger = $container->get('logger.factory')->get('webform_email_confirm');
    $instance->languageManager = $container->get('language_manager');
    $instance->tempstore = $container->get('tempstore.shared')->get('webform_email_confirm');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(WebformSubmissionInterface $webform_submission) {
    $data = $webform_submission->getData();
    $original_data = $webform_submission->getOriginalData();
    $elements = $this->webform->getElementsInitializedFlattenedAndHasValue();
    $email_confirm_elements = [];

    // Check webform elements for `email`s that require confirmation.
    foreach ($elements as $element_id => $element) {
      if ($element['#webform_plugin_id'] === 'email' && !empty($element['#requires_email_confirmation']) && !empty($element['#confirmation_status_element'])) {
        $email_confirm_element = new \stdClass();
        $email_confirm_element->element_id = $element_id;
        $email_confirm_element->confirmation_status_element = $element['#confirmation_status_element'];
        $email_confirm_elements[] = $email_confirm_element;
      }
    }

    if (empty($email_confirm_elements)) {
      return;
    }

    foreach ($email_confirm_elements as $email_confirm_element) {
      $email = $data[$email_confirm_element->element_id];
      $email_orig = $original_data[$email_confirm_element->element_id] ?? '';

      // If the email address has changed, revert the confirmation status. The
      // postSave() method will also notice the email address change and send a
      // new confirmation email.
      if ($webform_submission->isNew()) {
        $data[$email_confirm_element->confirmation_status_element] = 'pending';
        $webform_submission->setData($data);
      }
      elseif ($email !== $email_orig) {
        $data[$email_confirm_element->confirmation_status_element] = 'invalid';
        $webform_submission->setData($data);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    $data = $webform_submission->getData();
    $original_data = $webform_submission->getOriginalData();
    $elements = $this->webform->getElementsInitializedFlattenedAndHasValue();
    $email_confirm_elements = [];

    // Check webform elements for `email`s that require confirmation.
    foreach ($elements as $element_id => $element) {
      if ($element['#webform_plugin_id'] === 'email' && !empty($element['#requires_email_confirmation']) && !empty($element['#confirmation_status_element'])) {
        $email_confirm_element = new \stdClass();
        $email_confirm_element->element_id = $element_id;
        $email_confirm_element->confirmation_status_element = $element['#confirmation_status_element'];
        $email_confirm_elements[] = $email_confirm_element;
      }
    }

    if (empty($email_confirm_elements)) {
      return;
    }

    // Send a confirmation message to each email address that requires it.
    foreach ($email_confirm_elements as $email_confirm_element) {
      $email = $data[$email_confirm_element->element_id];

      if (empty($email)) {
        continue;
      }

      $email_orig = $original_data[$email_confirm_element->element_id] ?? '';
      $email_new = $update === FALSE;
      $email_changed = $update === TRUE && $email !== $email_orig;

      if ($email_new || $email_changed) {
        // Generate a unique confirmation token.
        $token = Crypt::randomBytesBase64(32);

        // Create a TempStore record of this pending email confirmation.
        $tempstore_key = 'submission_confirmation_' . $token;
        $confirmation_data = new \stdClass();
        $confirmation_data->sid = $webform_submission->id();
        $confirmation_data->confirmation_status_element = $email_confirm_element->confirmation_status_element;
        $this->tempstore->set($tempstore_key, $confirmation_data);

        // Send an email with a link containing the confirmation token.
        $current_langcode = $this->languageManager->getCurrentLanguage()->getId();
        $site_settings = $this->configFactory->get('system.site');
        $params = [];
        $params['token'] = $token;

        $this->mailManager->mail('webform_email_confirm', 'email_confirm_message', $email, $current_langcode, $params, $site_settings->get('mail'));

        $this->logger->notice('Email confirmation message sent for webform submission @webform_submission', [
          '@webform_submission' => $webform_submission->id(),
        ]);
      }
    }
  }

}
