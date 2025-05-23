<?php

namespace Drupal\ilr\Plugin\ExtraField\Display;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\extra_field\Plugin\ExtraFieldDisplayBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
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

  public function __construct(
    array $configuration,
    protected string $plugin_id,
    protected mixed $plugin_definition,
    protected ConfigFactoryInterface $config,
    protected EntityTypeManagerInterface $entityTypeManager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
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
        'session_dates' => [],
        'instructors' => [],
        'course' => $class->field_course->entity,
        'register_url' => '',
        'price' => $class->field_price->value,
        'discount_price' => $class->ilroutreach_discount_price->value ?? $class->field_price->value,
        'discount_enddate' => $class->ilroutreach_discount_date->end_value,
      ];

      foreach ($class->sessions as $session) {
        $session_date = new \stdClass;
        $session_date->start = new DrupalDateTime($session->entity->session_date->value, DateTimeItemInterface::STORAGE_TIMEZONE);
        $session_date->end = new DrupalDateTime($session->entity->session_date->end_value, DateTimeItemInterface::STORAGE_TIMEZONE);
        $info['session_dates'][] = $session_date;
      }

      foreach ($this->getClassInstructors($class) as $instructor) {
        $info['instructors'][] = $instructor;
      }

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

  private function getClassInstructors(EntityInterface $class_entity): array {
    $instructors = [];
    $node_storage = $this->entityTypeManager->getStorage('node');
    $query = $node_storage->getQuery();
    $query
      ->accessCheck(TRUE)
      ->condition('type', 'participant')
      ->condition('status', 1)
      ->condition('field_class', $class_entity->id())
      ->condition('field_class.entity:node.field_date_end', date('Y-m-d'), '>')
      ->sort('field_class.entity:node.field_date_start');
    $participant_results = $query->execute();
    $participants = $node_storage->loadMultiple($participant_results);

    foreach ($participants as $participant) {
      $instructors[] = $participant->field_instructor->entity;
    }

    return $instructors;
  }

}
