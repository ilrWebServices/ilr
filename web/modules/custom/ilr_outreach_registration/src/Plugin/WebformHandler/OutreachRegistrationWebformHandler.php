<?php

namespace Drupal\ilr_outreach_registration\Plugin\WebformHandler;

use Drupal\Core\Queue\QueueInterface;
use Drupal\ilr_outreach_registration\WebformSubmissionSerializer;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Webform handler to submit registration submissions to Salesforce webhook.
 *
 * @WebformHandler(
 *   id = "outreach_registration_submitter",
 *   label = @Translation("ILR Outreach registration order submitter"),
 *   description = @Translation("Submits registration form submissions as orders to Salesforce."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_IGNORED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 * )
 */
class OutreachRegistrationWebformHandler extends WebformHandlerBase {

  protected WebformSubmissionSerializer $serializer;
  protected QueueInterface $queue;
  protected LoggerInterface $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->serializer = $container->get('ilr_outreach_registration.webform_submission_serializer');
    $instance->queue = $container->get('queue')->get('serialized_order_to_salesforce');
    $instance->logger = $container->get('logger.factory')->get('serialized_order_to_salesforce');
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
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    $data = $webform_submission->getData();

    if (empty($data['eventid'])) {
      // @todo Log this.
      return;
    }

    // Only submit new form submissions to SF, not edits to existing ones.
    if ($update) {
      return;
    }

    // Add some submission data to the serialized order.
    $serialized_order = $this->serializer->generateEventRegistrationWebRegPayload($webform_submission);

    // Queue the serialized order for submission to the WebReg webhook on
    // Salesforce.
    $queue_item_id = $this->queue->createItem($serialized_order);

    $this->logger->notice('Outgoing Salesforce WebReg webhook request queued for webform submission @webform_submission', [
      '@webform_submission' => $webform_submission->id(),
    ]);
  }

}
