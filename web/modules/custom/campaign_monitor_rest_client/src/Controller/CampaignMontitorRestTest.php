<?php

namespace Drupal\campaign_monitor_rest_client\Controller;

use Drupal\Core\Controller\ControllerBase;
use CampaignMonitor\CampaignMonitorRestClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * An example controller.
 */
class CampaignMontitorRestTest extends ControllerBase {

  private $client;

  public function __construct(CampaignMonitorRestClient $campaign_monitor_rest_client) {
    $this->client = $campaign_monitor_rest_client;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('campaign_monitor_rest_client')
    );
  }

  /**
   * Returns a render-able array for a test page.
   */
  public function content() {
    try {
      $res = $this->client->get('clients.json');
    }
    catch (\Exception $e) {
      return [];
    }

    if ($res->getHeaderLine('Content-Type') === 'application/json') {
      $data = $res->getData();
      dump($data);
      $client_id = $data[0]['ClientID'] ?? FALSE;
      dump($client_id);
    }

    $res = $this->client->get("clients/$client_id/lists.json");
    dump($res->getData());
    $list_id = $res->getData()[0]['ListID'];

    $segment_call = $this->client->get("lists/$list_id/segments.json");
    $segments = $segment_call->getData();
    dump($segments);

    $data = [
      'json' => [
        'Name' => 'Course notification for ' . 'test 3',
        'Subject' => 'New date announced for ' . 'test',
        'FromName' => 'ILR Customer Service',
        'FromEmail' => 'ilrcustomerservice@cornell.edu',
        'ReplyTo' => 'ilrcustomerservice@cornell.edu',
        'HtmlUrl' => 'http://example.com/',
        'SegmentIDs' => [$segments[0]['SegmentID']],
      ],
    ];

    dump($data);
    dump($client_id);

    // dump($send_date->format('Y-m-d H:i'));

    $response = $this->client->post("campaigns/$client_id.json", $data);
    $campaign_id = $response->getData();

    dump($campaign_id);

    $send_date = new \DateTime('tomorrow');
    $send_date->setTime(9, 01);

    $data = [
      'json' => [
        'ConfirmationEmail' => 'ilrweb@cornell.edu',
        'SendDate' => $send_date->format('Y-m-d H:i'),
      ],
    ];

    $this->client->post("campaigns/$campaign_id/send.json", $data);

    $build = [
      '#markup' => $this->t('Hello World!'),
    ];

    return $build;
  }

}
