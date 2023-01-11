<?php

namespace Drupal\webform_email_confirm\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\TempStore\SharedTempStore;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for webform email confirm route(s).
 */
class EmailConfirm extends ControllerBase {

  /**
   * The shared tempstore.
   */
  protected SharedTempStore $tempstore;

  /**
   * The date formatter.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * Constructs an EmailConfirm object.
   */
  public function __construct(SharedTempStore $tempstore, DateFormatterInterface $date_formatter) {
    $this->tempstore = $tempstore;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.shared')->get('webform_email_confirm'),
      $container->get('date.formatter')
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
  public function confirm(string $token, Request $request) {
    $confirmation_data = $this->tempstore->get('submission_confirmation_' . $token);

    if ($confirmation_data->sid) {
      /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
      $webform_submission = $this->entityTypeManager()->getStorage('webform_submission')->load($confirmation_data->sid);
      $data = $webform_submission->getData();
      $confirmation_status = $data[$confirmation_data->confirmation_status_element];

      if (isset($confirmation_status) && $confirmation_status !== 'confirmed') {
        $data[$confirmation_data->confirmation_status_element] = 'confirmed';
        $webform_submission->setData($data);

        // Log the confirmation as a note on the submission.
        $request_time = $request->server->get('REQUEST_TIME');
        $note = strtr('Email confirmation: @email at @datetime', [
          '@email' => $confirmation_data->email,
          '@datetime' => $this->dateFormatter->format($request_time, 'custom', 'Y-m-d h:i:s'),
        ]);
        $notes = $webform_submission->getNotes();
        $notes .= ($notes ? PHP_EOL : '') . $note;
        $webform_submission->setNotes($notes);

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
