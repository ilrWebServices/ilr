<?php

namespace Drupal\campaign_monitor_rest_client\Controller;

use Drupal\Core\Controller\ControllerBase;
use CampaignMonitor\CampaignMonitorRestClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * An example controller.
 */
class CampaignMontitorRestTest extends ControllerBase {

  private $campaignMonitorRestClient;

  public function __construct(CampaignMonitorRestClient $campaign_monitor_rest_client) {
    $this->campaignMonitorRestClient = $campaign_monitor_rest_client;
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
      $res = $this->campaignMonitorRestClient->get('clients.json');
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

    $build = [
      '#markup' => $this->t('Hello World!'),
    ];

    return $build;
  }

}
