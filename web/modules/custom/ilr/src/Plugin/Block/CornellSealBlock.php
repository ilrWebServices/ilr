<?php

namespace Drupal\ilr\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'CornellSealBlock' block.
 *
 * @Block(
 *   id = "cornell_seal_block",
 *   admin_label = @Translation("Cornell Seal"),
 * )
 */
class CornellSealBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['#cache']['contexts'] = ['url.path'];

    $build['cornell_seal'] = [
      '#type' => 'link',
      '#url' => Url::fromUri('https://www.cornell.edu'),
      '#title' => [
        '#theme' => 'image',
        '#uri' => theme_get_setting('logo.url'),
        '#alt' => $this->t('Cornell University Home'),
      ],
    ];

    $build['cornell_seal']['#attributes']['class'][] = 'cornell-seal';

    return $build;
  }

}
