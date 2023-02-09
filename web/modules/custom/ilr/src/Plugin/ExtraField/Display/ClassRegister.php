<?php

namespace Drupal\ilr\Plugin\ExtraField\Display;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\extra_field\Plugin\ExtraFieldDisplayBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Course Class Register Extra field Display.
 *
 * @ExtraFieldDisplay(
 *   id = "class_register",
 *   label = @Translation("Class Register"),
 *   bundles = {
 *     "node.course",
 *   },
 *   visible = true
 * )
 */
class ClassRegister extends ExtraFieldDisplayBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->pathAliasEntitiesManager = $container->get('path_alias.entities');
    $instance->config = $container->get('config.factory');
    $instance->discountManager = $container->get('ilr_outreach_discount_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function view(ContentEntityInterface $node) {
    $build = [];
    $classes = $this->removeOverflowSessions($node->classes->referencedEntities());
    $class_info = [];

    foreach ($classes as $class) {
      $info = [
        'entity' => $class,
        'course' => $class->field_course->entity,
        'register_url' => '',
        'price' => $class->field_price->value,
        'discount_price' => $class->ilroutreach_discount_price->value,
        'discount_enddate' => $class->ilroutreach_discount_date->end_value,
      ];

      if ($mapped_object = $class->getClassNodeSalesforceMappedObject()) {
        $info['register_url'] = $this->config->get('ilr_registration_system.settings')->get('url') . $mapped_object->salesforce_id->getString();
      }

      if (!$class->field_external_link->isEmpty()) {
        $info['register_url'] = $class->field_external_link->uri;
      }

      $class_info[] = $info;
    }

    $build = [
      '#theme' => 'ilr_class_register_block',
      '#classes' => $class_info,
      '#cache' => [
        // @todo Figure out why this doesn't actually cache when testing locally.
        'max-age' => 300,
      ],
    ];

    return $build;
  }

  /**
   * Removes the overflow sessions.
   *
   * If a class session is full, there are times when additional
   * sessions at the same date/time are created to accommodate
   * more participants. In such cases, we should remove
   * the full class session from the display to avoid confusion.
   *
   * @param array $classes
   *   The full list of class entities.
   *
   * @return array
   *   The list of class entities with overflow sessions removed.
   */
  private function removeOverflowSessions(array $classes) {
    if (count($classes) == 1) {
      return $classes;
    }

    $possible_duplicates = [];

    // Loop through and check date/location for classes.
    foreach ($classes as $class) {
      $city = (!empty($class->field_address->locality))
        ? $class->field_address->locality
        : 'online';
      $time_and_place = $class->field_date_start->value . $city;
      $class_info = [$time_and_place => $class];

      // If there is already a class with the same time_place
      // turn that value into an array.
      if (array_key_exists($time_and_place, $possible_duplicates)) {
        $first = $possible_duplicates[$time_and_place];
        $possible_duplicates[$time_and_place] = [$first, $class];
      }
      else {
        $possible_duplicates[$time_and_place] = $class;
      }
    }

    // Remove the full class from the display.
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
