<?php

namespace Drupal\ilr_campaigns\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ilr_campaigns\CourseNotificationHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Updates course notification subscribers from webform data.
 *
 * @QueueWorker(
 *   id = "course_notification_subscriber",
 *   title = @Translation("Course Notification Subscriber"),
 *   cron = {"time" = 90}
 * )
 */
class CourseNotificationSubscriber extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The course notification helper service.
   *
   * @var \Drupal\ilr_campaigns\CourseNotificationHelper
   */
  protected $courseNotificationHelper;

  /**
   * Constructs a CourseNotificationSubscriber queue object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\ilr_campaigns\CourseNotificationHelper $course_notification_helper
   *   Helper service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CourseNotificationHelper $course_notification_helper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->courseNotificationHelper = $course_notification_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ilr_campaigns.course_notifications')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $this->courseNotificationHelper->processSubscriber($data);
  }

}
