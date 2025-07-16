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

    // Overrides the grist_link_log related arguments in sites/default/monolog.services.yml.
    if ($container->hasDefinition('monolog.handler.grist_link_log')) {
      $definition = $container->getDefinition('monolog.handler.grist_link_log');
      $definition->setArgument('$gristDocument', getenv('GRIST_LINK_LOG_DOCUMENT_URL'));
      $definition->setArgument('$gristApiToken', getenv('GRIST_LINK_LOG_API_TOKEN'));
    }
  }

}
