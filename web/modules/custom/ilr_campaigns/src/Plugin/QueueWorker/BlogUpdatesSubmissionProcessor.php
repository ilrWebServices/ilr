<?php

namespace Drupal\ilr_campaigns\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ilr_campaigns\BlogSubscriptionHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Updates course notification subscribers from webform data.
 *
 * @QueueWorker(
 *   id = "blog_updates_submission_processor",
 *   title = @Translation("Blog updates submission processor"),
 *   cron = {"time" = 90}
 * )
 */
class BlogUpdatesSubmissionProcessor extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The blog subscription helper service.
   *
   * @var \Drupal\ilr_campaigns\BlogSubscriptionHelper
   */
  protected $blogSubscriptionHelper;

  /**
   * Constructs a BlogSubscriptionSubmissionProcessor queue object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\ilr_campaigns\BlogSubscriptionHelper $blog_subscription_helper
   *   Helper service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, BlogSubscriptionHelper $blog_subscription_helper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->blogSubscriptionHelper = $blog_subscription_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ilr_campaigns.blog_subscriptions')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $this->blogSubscriptionHelper->processSubscriber($data);
  }

}
