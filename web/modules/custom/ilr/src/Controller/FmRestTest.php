<?php

namespace Drupal\ilr\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\RouteMatch;

/**
 * .
 */
class FmRestTest extends ControllerBase {

  /**
   * A FileMaker Server REST client
   *
   * @var \Drupal\fmrest\RestClient
   */
  protected $fmrestClient;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->fmrestClient = $container->get('fmrest.client');
    return $instance;
  }

  /**
   *
   */
  public function response(RouteMatch $route_match) {
    $this->fmrestClient->setServer('cornell_filemaker_server_1');
    dump($this->fmrestClient);
    $response = $this->fmrestClient->apiCall('POST', '/fmi/data/{{version}}/databases/{{database}}/layouts/Opportunities/_find', [
      'query' => [
        ['Program Year' => '2019-2020'],
      ],
    ]);
    dump(json_decode($response->getBody(), TRUE));

    $build = [
      '#markup' => $this->t('Hello World!!!'),
      '#cache' => [
        'max-age' => 0, // Never cache!
      ],
    ];
    return $build;
  }

}
