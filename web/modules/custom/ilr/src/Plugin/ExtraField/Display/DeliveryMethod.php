<?php

namespace Drupal\ilr\Plugin\ExtraField\Display;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\extra_field\Plugin\ExtraFieldDisplayBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ilr\Entity\EventLandingNode;
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
    if (!$node instanceof EventLandingNode) {
      return [];
    }

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
        '#markup' => $node->deliveryMethod(),
      ],
      '#cache' => [
        'tags' => ['node_list:event_landing_page'],
      ],
    ];

    return $build;
  }
}
