<?php

namespace Drupal\ilr;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Modifies various services (with caution).
 */
class IlrServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Overrides email.validator class to add DNS validation for email.
    if ($container->hasDefinition('email.validator')) {
      $definition = $container->getDefinition('email.validator');
      $definition->setClass('Drupal\ilr\EmailValidator');
    }
  }

}
