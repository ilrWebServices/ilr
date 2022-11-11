<?php

namespace Drupal\ilr_outreach_registration\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\webform\WebformInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for the incoming salesforce return webhook.
 */
class SalesforcePingbackWebformWebhook extends ControllerBase {

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Webform submission storage.
   *
   * @var \Drupal\webform\WebformSubmissionStorage
   */
  protected $submissionStorage;

  /**
   * Constructs a new SalesforcePingbackWebformWebhook object.
   */
  public function __construct() {
    $this->logger = $this->getLogger('serialized_order_to_salesforce');
    $this->submissionStorage = $this->entityTypeManager()->getStorage('webform_submission');
  }

  /**
   * Receive and process incoming salesforce-pingback webhook requests.
   *
   * @param \Drupal\webform\WebformInterface $webform
   *   The webform from the route parameter.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request with the salesforce mapping payload.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON-encoded status response.
   */
  public function v1(WebformInterface $webform, Request $request) {
    $this->logger->notice('Incoming salesforce webhook data received: @data', [
      '@data' => $request->getContent(),
    ]);

    $data = json_decode($request->getContent(), TRUE);

    /** @var \Drupal\webform\WebformSubmissionInterface $submission */
    $submission = $this->submissionStorage->load($data['pos_order_id'] ?? 0);

    if (!$submission || $submission->webform_id->target_id !== $webform->id()) {
      $this->logger->error('No such submission @submission_id for webform @webform_id', [
        '@submission_id' => $data['pos_order_id'] ?? 0,
        '@webform_id' => $webform->id(),
      ]);

      return new JsonResponse([
        'status' => JsonResponse::HTTP_BAD_REQUEST,
        'message' => 'No such submission.',
      ], JsonResponse::HTTP_BAD_REQUEST);
    }

    $notes = $submission->getNotes();
    $notes .= ($notes ? PHP_EOL : '') . 'Salesforce order: ' . ($data['sf_order_id'] ?? 'missing');
    $submission->setNotes($notes);
    $submission->save();

    return new JsonResponse([
      'status' => JsonResponse::HTTP_OK,
      'message' => 'Data recieved.',
    ], JsonResponse::HTTP_OK);
  }

}
