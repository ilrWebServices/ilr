<?php

namespace Drupal\ilr_migrate\Plugin\migrate\source;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Custom source fabricated from calls to the eCornell API.
 *
 * @MigrateSource(
 *   id = "ecornell_remote_program",
 * )
 */
class ECornellRemoteProgram extends SourcePluginBase implements ContainerFactoryPluginInterface {

  /**
   * Data obtained from the source plugin constructor.
   *
   * @var array[]
   *   Array of data rows, each one an array of values keyed by field names.
   */
  protected $dataRows = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MigrationInterface $migration,
    protected ClientInterface $httpClient
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
    $this->fetchData();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function fields() : array {
    return [
      'id' => $this->t('Certificate ID'),
      'title' => $this->t('Certificate Title'),
      'description' => $this->t('Certificate Description'),
      'delivery_method' => $this->t('Certificate Delivery Method'),
      'next_course_start_date' => $this->t('Next Course Start Date'),
      'cost' => $this->t('Certificate Cost'), // TODO: Is this info in the API?
      'url' => $this->t('Certificate URL'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() : array {
    return [
      'id' => [
        'type' => 'string',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() : \Iterator {
    return new \ArrayIterator($this->dataRows);
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return 'External eCornell Certificates';
  }

  /**
   * Undocumented function
   */
  protected function fetchData() : void {
    $catalog_data = $this->apiCall('catalog?include=titles');

    if (!isset($catalog_data['certificates_titles'])) {
      return;
    }

    foreach ($catalog_data['certificates_titles'] as $cert_code => $cert_title) {
      $cert_data = [
        'id' => $cert_code,
        'title' => $cert_title,
      ];

      $cert_data_details = $this->apiCall('certificate/' . $cert_code . '?include=sections&include=titles');
      // dump($cert_data_details);

      $cert_data['description'] = $cert_data_details['description'] ?? '';
      $cert_data['url'] = $cert_data_details['url'] ?? '';
      $this->dataRows[] = $cert_data;
    }
  }

  protected function apiCall(string $path, int $ttl = 60 * 60) : array {
    $cid = 'ecornell_certificate_data:' . $path;

    if ($cache_data = \Drupal::cache()->get($cid)) {
      return $cache_data->data;
    }

    // Make the request
    try {
      $url_base = getenv('ECORNELL_API_URL_BASE');
      $url = $url_base . $path;
      $client_code = getenv('ECORNELL_API_CLIENT_CODE');
      $secret = getenv('ECORNELL_API_SECRET');
      $timestamp = time() * 1000;
      $auth_hash = md5(preg_replace('|\?.*$|', '', $path) . $client_code . $timestamp . $secret);

      $response = $this->httpClient->request('GET', $url, [
        'headers' => [
          'Authorization' => "$client_code.$timestamp.$auth_hash",
          'Accept' => 'application/json',
        ]
      ]);
    }
    catch (\Exception $e) {
      return [];
    }

    $remote_data = json_decode($response->getBody()->getContents(), TRUE);
    \Drupal::cache()->set($cid, $remote_data, time() + $ttl);
    return $remote_data;
  }

}
