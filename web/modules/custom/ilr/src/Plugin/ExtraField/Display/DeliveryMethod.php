<?php

namespace Drupal\ilr\Plugin\ExtraField\Display;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\extra_field\Plugin\ExtraFieldDisplayBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ilr\Entity\EventNodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Course Class Register Extra field Display.
 *
 * @ExtraFieldDisplay(
 *   id = "delivery_method",
 *   label = @Translation("Delivery method"),
 *   bundles = {
 *     "node.event_landing_page",
 *   },
 *   visible = true
 * )
 */
class DeliveryMethod extends ExtraFieldDisplayBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  public function __construct(
    array $configuration,
    protected string $plugin_id,
    protected mixed $plugin_definition,
    protected ConfigFactoryInterface $config
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
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function view(ContentEntityInterface $node) {
    if (!$node instanceof EventNodeInterface) {
      return [];
    }

    if ($node->hasField('field_delivery_method') && $node->field_delivery_method->isEmpty()) {
      return [];
    }

    $build = [];

    if ($delivery_method = $node->deliveryMethod()) { // @see Drupal\ilr\Entity\EventLandingNode;
      $build = [
        '#theme' => 'field',
        '#label_display' => 'hidden',
        '#title' => $this->t('Delivery Method'),
        '#field_name' => 'field_delivery_method',
        '#field_type' => 'extra_field',
        '#field_translatable' => TRUE,
        '#entity_type' => 'node',
        '#bundle' => 'event_landing_page',
        '#is_multiple' => FALSE,
        '#view_mode' => '_custom',
        '#object' => $node,
        '0' => [
          '#markup' => $delivery_method,
        ],
        '#cache' => [
          'tags' => ['node_list:event_landing_page'],
        ],
      ];
    }

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
