<?php

namespace Drupal\campaign_monitor_rest_client\Http;

use CampaignMonitor\CampaignMonitorRestClient;
use GuzzleHttp\HandlerStack;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Helper class to construct a Campaign Montitor REST client.
 */
class CampaignMonitorRestClientFactory {

  /**
   * The handler stack.
   *
   * @var \GuzzleHttp\HandlerStack
   */
  protected $stack;

  /**
   * The campaign monitor configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $settings;

  /**
   * Constructs a new CampaignMonitorRestClientFactory instance.
   *
   * @param \GuzzleHttp\HandlerStack $stack
   *   The handler stack.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(HandlerStack $stack, ConfigFactoryInterface $config_factory) {
    $this->stack = $stack;
    $this->settings = $config_factory->get('campaign_monitor_rest_client.settings');
  }

  /**
   * Constructs a new client object from some configuration.
   *
   * @param array $config
   *   The config for the client.
   *
   * @return \CampaignMonitor\CampaignMonitorRestClient
   *   The Campaign Monitor REST HTTP client.
   */
  public function fromOptions(array $config = []) {
    // See \Drupal\Core\Http\ClientFactory::fromOptions(). We can't extend that
    // class, though, because it returns a new GuzzleHttp client.
    $default_config = [
      // Security consideration: we must not use the certificate authority
      // file shipped with Guzzle because it can easily get outdated if a
      // certificate authority is hacked. Instead, we rely on the certificate
      // authority file provided by the operating system which is more likely
      // going to be updated in a timely fashion. This overrides the default
      // path to the pem file bundled with Guzzle.
      'verify' => TRUE,
      'timeout' => 30,
      'headers' => [
        'User-Agent' => 'Drupal/' . \Drupal::VERSION . ' (+https://www.drupal.org/) ' . \GuzzleHttp\default_user_agent(),
      ],
      'handler' => $this->stack,
      // Security consideration: prevent Guzzle from using environment variables
      // to configure the outbound proxy.
      'proxy' => [
        'http' => NULL,
        'https' => NULL,
        'no' => [],
      ],
    ];

    // Add the api_key to the config.
    $config['api_key'] = $this->settings->get('api_key');

    return new CampaignMonitorRestClient($config);
  }

}
