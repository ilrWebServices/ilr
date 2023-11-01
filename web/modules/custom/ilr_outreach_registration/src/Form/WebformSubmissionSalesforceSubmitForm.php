<?php

namespace Drupal\ilr_outreach_registration\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Queue\DatabaseQueue;
use Drupal\Core\Queue\QueueInterface;
use Drupal\ilr_outreach_registration\WebformSubmissionSerializer;
use Drupal\webform\WebformSubmissionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class WebformSubmissionSalesforceSubmitForm extends FormBase {

  public function __construct(
    protected WebformSubmissionSerializer $serializer,
    protected QueueInterface $queue,
    protected LoggerInterface $logger,
    protected Connection $connection,
    protected ?WebformSubmissionInterface $webformSubmission = NULL
  ) {}

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ilr_outreach_registration.webform_submission_serializer'),
      $container->get('queue')->get('serialized_order_to_salesforce'),
      $container->get('logger.factory')->get('serialized_order_to_salesforce'),
      $container->get('database')
    );
  }

  public function getFormId() {
    return 'webform_submission_salesforce_submit';
  }

  public function title(WebformSubmissionInterface $webform_submission = NULL) {
    return $this->t('Send form submission #@webform_submission to Salesforce', [
      '@webform_submission' => $webform_submission->id(),
    ]);
  }

  public function buildForm(array $form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission = NULL) {
    $this->webformSubmission = $webform_submission;
    $in_queue = FALSE;

    if ($this->queue instanceof DatabaseQueue) {
      $webhook_payload_data = $this->serializer->generateEventRegistrationWebRegPayload($this->webformSubmission);

      try {
        $duplicate_data_count = $this->connection->query('SELECT COUNT([item_id]) FROM {' . DatabaseQueue::TABLE_NAME . '} WHERE [data] = :data', [':data' => serialize($webhook_payload_data)])->fetchField();
        $in_queue = $duplicate_data_count > 0;
      }
      catch (\Exception $e) {
        // Fail silently.
      }
    }

    $form['information'] = [
      '#type' => 'webform_submission_information',
      '#webform_submission' => $webform_submission,
    ];

    $form['payload'] = [
      '#type' => 'details',
      '#title' => $this->t('Payload'),
    ];

    $form['payload']['data'] = [
      '#markup' => '<pre>' . json_encode($webhook_payload_data, JSON_PRETTY_PRINT) . '</pre>',
    ];

    $form['notes'] = [
      '#type' => 'inline_template',
      '#template' => '<h2>Notes</h2><pre>{{ notes }}</pre><p>Note: Adding this item to the queue may create a duplicate order in Salesforce.</p><br/>',
      '#context' => [
        'notes' => $webform_submission->getNotes(),
      ],
      '#access' => (bool) $webform_submission->getNotes()
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#default_value' => $this->t('Add to queue'),
      '#disabled' => $in_queue,
    ];

    if ($in_queue) {
      $this->messenger()->addStatus($this->t('Submission #@webform_submission is queued for processing.', [
        '@webform_submission' => $webform_submission->id(),
      ]));
    }

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $webhook_payload_data = $this->serializer->generateEventRegistrationWebRegPayload($this->webformSubmission);

    // Queue the serialized order for submission to the WebReg webhook on
    // Salesforce.
    $queue_item_id = $this->queue->createItem($webhook_payload_data);

    $this->logger->notice('Outgoing Salesforce WebReg webhook request manually queued for webform submission @webform_submission', [
      '@webform_submission' => $this->webformSubmission->id(),
    ]);
  }

}
