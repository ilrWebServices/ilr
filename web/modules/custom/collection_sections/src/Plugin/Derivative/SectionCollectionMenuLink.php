<?php

namespace Drupal\collection_sections\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derivative class that provides the menu links for Content Section collections.
 */
class SectionCollectionMenuLink extends DeriverBase implements ContainerDeriverInterface {

   /**
   * @var EntityTypeManagerInterface $entityTypeManager.
   */
  protected $entityTypeManager;

  /**
   * Creates a SectionCollectionMenuLink instance.
   *
   * @param $base_plugin_id
   * @param EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct($base_plugin_id, EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $base_plugin_id,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $links = [];

    $collections = $this->entityTypeManager->getStorage('collection')->loadByProperties([
      'type' => 'content_section',
    ]);

    foreach ($collections as $collection) {
      $links[$collection->uuid()] = [
        'title' => $collection->label(),
        'route_name' => $collection->toUrl()->getRouteName(),
        'route_parameters' => ['collection' => $collection->id()],
      ] + $base_plugin_definition;
    }

    return $links;
  }
}
