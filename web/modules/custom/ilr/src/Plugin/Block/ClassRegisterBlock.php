<?php

namespace Drupal\ilr\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'ClassRegisterBlock' block.
 *
 * @Block(
 *  id = "class_register_block",
 *  admin_label = @Translation("Class register"),
 * )
 */
class ClassRegisterBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity manager.
   *
   * @var Drupal\Core\Entity\EntityManagerInterface;
   */
  protected $entityManager;

  /**
   * Constructs a new ClassRegisterBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param $entity_manager
   *   The entity manager object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityManagerInterface $entity_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    if (!$node = \Drupal::routeMatch()->getParameter('node')) {
      return $build;
    }

    if ($node->bundle() != 'course' || !$node->hasField('field_topics')) {
      return $build;
    }

    $classes = $node->classes->referencedEntities();

    $class_info = [];
    foreach ($classes as $class) {
      $mapped_objects = $this->entityManager->getStorage('salesforce_mapped_object')
        ->loadByProperties([
          'drupal_entity__target_type' => 'node',
          'drupal_entity__target_id' => $class->id(),
          'salesforce_mapping' => 'class_node'
        ]);

      $mapping = reset($mapped_objects);

      if (empty($mapping)) {
        continue;
      }

      $class_info[] = [
        'salesforce_id' => $mapping->salesforce_id->getString(),
        'entity' => $class
      ];
    }

    $build = [
      '#theme' => 'ilr_class_register_block',
      '#classes' => $class_info
    ];

    return $build;
  }

}
