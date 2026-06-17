<?php

namespace Drupal\ilr_migrate\Plugin\migrate\source;

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
class ECornellRemoteProgram extends SourcePluginBase {

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
  public function fields(): array {
    return [
      'id' => $this->t('Certificate ID'),
      'title' => $this->t('Certificate Title'),
      'description' => $this->t('Certificate Description'),
      'code' => $this->t('Certificate Code'),
      'delivery_method' => $this->t('Certificate Delivery Method'),
      'next_course_start_date' => $this->t('Next Course Start Date'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds(): array {
    return [
      'id' => [
        'type' => 'string',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator(): \Iterator {
    $data = [];
    return new \ArrayIterator($data);
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return 'External eCornell Certificates';
  }

}
