<?php

namespace Drupal\ilr_instagram;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheBackendInterface;
use Psr\Log\LoggerInterface;
use GuzzleHttp\ClientInterface;
use Laminas\Feed\Reader\Reader;

/**
 * Process an RSS feed of Instagram posts.
 */
class InstagramFeedProcessor {

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

  public function getPosts($feed_url) {
    $posts = $this->cacheData->get('instagram_posts_' . hash('crc32', $feed_url));

    if ($posts) {
      return $posts->data;
    }

    $response = $this->httpClient->get($feed_url);

    if ($response->getStatusCode() !== 200) {
      $this->logger->error('Error code %code returned for %url.', [
        '%code' => $response->getStatusCode(),
        '%url' => $feed_url,
      ]);

      return $posts;
    }

    $feed = Reader::importString((string) $response->getBody());

    if (empty($feed)) {
      $this->logger->error('Missing data for %url.', [
        '%url' => $feed_url,
      ]);

      return $posts;
    }

    $posts = [];

    foreach ($feed as $entry) {
      $posts[$entry->getLink()] = [
        'title'        => $entry->getTitle(),
        'description'  => $entry->getDescription(),
        'dateModified' => $entry->getDateModified(),
        'authors'      => $entry->getAuthors(),
        'link'         => $entry->getLink(),
        'content'      => $entry->getContent(),
        'enclosure'    => $entry->getEnclosure(),
      ];
    }

    // If the post data is found, cache it.
    if ($posts) {
      $tags = ['instagram_posts'];
      $this->cacheData->set('instagram_posts_' . hash('crc32', $feed_url), $posts, CacheBackendInterface::CACHE_PERMANENT, $tags);
      $this->logger->info('Cached posts for %url.', [
        '%url' => $feed_url,
      ]);
    }

    return $posts;
  }

}
