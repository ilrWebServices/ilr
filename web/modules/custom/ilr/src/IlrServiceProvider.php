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

    // Overrides the Slack webhook URL in sites/default/monolog.services.yml.
    if ($container->hasDefinition('monolog.handler.slack_webhook')) {
      $definition = $container->getDefinition('monolog.handler.slack_webhook');
      $definition->setArgument('$webhookUrl', getenv('SLACK_NOTIFICATIONS_WEBHOOK_URL'));
    }
  }

}
