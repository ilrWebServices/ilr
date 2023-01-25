<?php

namespace Drupal\ilr_outreach_registration\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Submits a webform event submission to Salesforce.
 *
 * @Action(
 *   id = "webform_submission_submit_outreach_registration_action",
 *   label = @Translation("Submit registration to ILR Outreach SF"),
 *   type = "webform_submission"
 * )
 */
class WebformSubmissionOutreachRegistrationAction extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    /** @var \Drupal\webform\WebformSubmissionInterface $entity */
    // $entity->setSticky(FALSE)->save();
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = $object->webform_id->entity;

    $handlers = $webform->getHandlers('outreach_registration_submitter');

    dump($handlers);

    /** @var \Drupal\webform\WebformSubmissionInterface $object */
    $result = $object->sticky->access('edit', $account, TRUE)
      ->andIf($object->access('update', $account, TRUE));

    return $return_as_object ? $result : $result->isAllowed();
  }

}
