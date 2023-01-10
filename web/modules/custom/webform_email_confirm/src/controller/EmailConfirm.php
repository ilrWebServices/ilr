<?php

namespace Drupal\webform_email_confirm\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\TempStore\SharedTempStore;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for webform email confirm route(s).
 */
class EmailConfirm extends ControllerBase {

  /**
   * The shared tempstore.
   *
   * @var \Drupal\Core\TempStore\SharedTempStore
   */
  protected $tempstore;

  /**
   * Constructs an EmailConfirm object.
   *
   * @param \Drupal\Core\TempStore\SharedTempStore $tempstore
   */
  public function __construct(SharedTempStore $tempstore) {
    $this->tempstore = $tempstore;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.shared')->get('webform_email_confirm')
    );
  }

  /**
   * Process webform email confirmation links.
   *
   * Note that this is a GET request, and is not technically idempotent.
   * However, after some research on the topic, many sources suggest that this
   * can be an exception to the rule. See the discussion at
   * https://stackoverflow.com/q/1066611
   */
  public function confirm($token) {
    $webform_submission_id_for_token = $this->tempstore->get('submission_confirmation_' . $token);

    if ($webform_submission_id_for_token) {
      /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
      $webform_submission = $this->entityTypeManager()->getStorage('webform_submission')->load($webform_submission_id_for_token);
      $data = $webform_submission->getData();

      if (isset($data['email_confirmation_status']) && empty($data['email_confirmation_status'])) {
        $data['email_confirmation_status'] = 1;
        $webform_submission->setData($data);

        if ($webform_submission->save()) {
          $this->tempstore->delete('submission_confirmation_' . $token);
          $this->messenger()->addMessage('Your email address has been confirmed. Thank you!');
          return new RedirectResponse($webform_submission->getSourceUrl()->toString());
        }
      }
    }

    return $this->redirect('<front>');
  }

}
