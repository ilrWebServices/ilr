<?php

namespace Drupal\campaign_monitor_transactional_email\Plugin\Mail;

use CampaignMonitor\CampaignMonitorRestClient;
use Drupal\Core\Mail\MailFormatHelper;
use Drupal\Core\Mail\MailInterface;
use Drupal\Core\Site\Settings;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\UnstructuredHeader;

/**
 * Defines the default Drupal mail backend, using PHP's native mail() function.
 *
 * @Mail(
 *   id = "campaign_monitor_transactional_mail",
 *   label = @Translation("Campaign Monitor Transactional Email mailer"),
 *   description = @Translation("Sends the message as plain text, using the Transactional Email API from Campaign Monitor.")
 * )
 */
class CampaignMonitorTransactionalEmail implements MailInterface {

  /**
   * The Campaign Montitor REST Client.
   */
  protected CampaignMonitorRestClient $client;

  /**
   * CampaignMonitorTransactionalEmail constructor.
   */
  public function __construct() {
    $this->client = \Drupal::service('campaign_monitor_rest_client');
  }

  /**
   * {@inheritdoc}
   */
  public function format(array $message) {
    // Join the body array into one string.
    $message['body'] = implode('\n\n', $message['body']);

    // Convert any HTML to plain-text.
    $message['body'] = MailFormatHelper::htmlToText($message['body']);
    // Wrap the mail body for sending.
    $message['body'] = MailFormatHelper::wrapMail($message['body']);

    return $message;
  }

  /**
   * {@inheritdoc}
   */
  public function mail(array $message) {
    $data = [
      'Subject' => $message['subject'],
      'From' => $message['from'],
      'ReplyTo' => $message['reply-to'],
      'To' => is_array($message['to']) ? $message['to'] : [$message['to']],
      // 'CC' => [
      //   'Joe Smith <joesmith@example.com>'
      // ],
      // 'BCC' => null,
      'Html' => '',
      'Text' => $message['body'],
      // 'Attachments' => [
      //   {
      //     'Type' => 'application/pdf',
      //     'Name' => 'Invoice.pdf',
      //     'Content' => 'base64encoded'
      //   }
      // ],
      'TrackOpens' => false,
      'TrackClicks' => false,
      'InlineCSS' => false,
      'Group' => 'Email confirmation',
      // 'AddRecipientsToListID' => '62eaaa0338245ca68e5e93daa6f591e9',
      'ConsentToTrack' => 'Unchanged'
    ];
    // @todo Add settings form for this.
    $client_id = '37db3749713df60ae6545c9b338e3210';
    $response = $this->client->post("transactional/classicEmail/send?clientID=$client_id", ['json' => $data]);
    dump($response);
  }

}
