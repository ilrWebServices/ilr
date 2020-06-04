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

    $classes = $this->removeOverflowSessions($node->classes->referencedEntities());

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

      // @tmp Skip in-person classes.
      if (strpos(strtolower($class->field_delivery_method->value), 'online') === FALSE) {
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

  /**
   * If a class session is full, there are times when additional
   * sessions at the same date/time are created to accommodate
   * more participants. In such cases, we should remove
   * the full class session from the display to avoid confusion.
   *
   * @param [Array] $classes
   * @return [Array] $classes
   */
  private function removeOverflowSessions($classes) {
    if (count($classes) == 1) {
      return $classes;
    }

    $possible_duplicates = [];

    // Loop through and check date/location for classes
    foreach ($classes as $class) {
      $city = (!empty($class->field_address->locality))
        ? $class->field_address->locality
        : 'online';
      $time_and_place = $class->field_date_start->value . $city;
      $class_info = [$time_and_place => $class];

      // If there is already a class with the same time_place
      // turn that value into an array
      if (array_key_exists($time_and_place, $possible_duplicates)) {
        $first = $possible_duplicates[$time_and_place];
        $possible_duplicates[$time_and_place] = [$first, $class];
      }
      else {
        $possible_duplicates[$time_and_place] = $class;
      }
    }

    // Remove the full class from the display
    foreach ($possible_duplicates as $classes_at_time_and_place) {
      if (is_array($classes_at_time_and_place)) {
        foreach ($classes_at_time_and_place as $class) {
          if ($class->field_class_full->value == 1) {
            if (($key = array_search($class, $classes)) !== FALSE) {
              unset($classes[$key]);
            }
          }
        }
      }
    }

    return $classes;
  }

}
