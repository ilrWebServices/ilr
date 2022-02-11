<?php

namespace Drupal\ilr;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Modifies the PageCache middleware service.
 */
class IlrServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('http_middleware.page_cache')) {
      $definition = $container->getDefinition('http_middleware.page_cache')->setClass('Drupal\ilr\StackMiddleware\PageCache');
    }
  }

}
