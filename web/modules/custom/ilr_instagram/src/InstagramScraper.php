<?php

namespace Drupal\ilr_instagram;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheBackendInterface;
use Psr\Log\LoggerInterface;
use GuzzleHttp\ClientInterface;

/**
 * Undocumented class
 */
class InstagramScraper {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The 'data' cache bucket.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheData;

  /**
   * The logger channel service. See the services.yml file.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new InstagramScraper service.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   HTTP client.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_data
   *   The 'data' cache bucket.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(ClientInterface $http_client, CacheBackendInterface $cache_data, LoggerInterface $logger) {
    $this->httpClient = $http_client;
    $this->cacheData = $cache_data;
    $this->logger = $logger;
  }

  public function getPosts($username) {
    $posts = $this->cacheData->get('instagram_posts_' . hash('crc32', $username));

    if ($posts) {
      return $posts->data;
    }

    $url = 'https://www.instagram.com/' . $username;
    $response = $this->httpClient->get($url);

    if ($response->getStatusCode() !== 200) {
      $this->logger->error('Error code %code returned for %url.', [
        '%code' => $response->getStatusCode(),
        '%url' => $url,
      ]);

      return $posts;
    }

    $html = (string) $response->getBody();

    if (empty($html)) {
      $this->logger->error('Missing HTML for %url.', [
        '%url' => $url,
      ]);

      return $posts;
    }

    $dom = new \DOMDocument();
    libxml_use_internal_errors(TRUE);
    $dom->loadHTML($html);
    libxml_use_internal_errors(FALSE);
    $xpath = new \DOMXPath($dom);
    $script_elements = $xpath->query('//script');
    $script = FALSE;

    foreach ($script_elements as $script_element) {
      $script_str = (string) $script_element->nodeValue;

      if (strpos($script_str, 'window._sharedData') === 0) {
        $script = $script_str;
        continue;
      }
    }

    // Clean up the script text, removing the variable setting and semicolon.
    $script = str_replace('window._sharedData = ', '', $script);
    $script = substr_replace($script, '', -1, 1);
    $data = Json::decode($script, TRUE);

    if (!$data) {
      $this->logger->error('Error decoding data for %url.', [
        '%url' => $url,
      ]);

      return $posts;
    }

    // If the post data is found, cache it.
    if (isset($data['entry_data']['ProfilePage']['0']['graphql']['user']['edge_owner_to_timeline_media']['edges'])) {
      $posts = $data['entry_data']['ProfilePage']['0']['graphql']['user']['edge_owner_to_timeline_media']['edges'];
      $tags = ['instagram_posts'];
      $this->cacheData->set('instagram_posts_' . hash('crc32', $username), $posts, CacheBackendInterface::CACHE_PERMANENT, $tags);
      $this->logger->info('Cached data for %url.', [
        '%url' => $url,
      ]);
    }

    return $posts;
  }

}
