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
 *   id = "new_class_processor",
 *   title = @Translation("New class processor"),
 *   cron = {"time" = 90}
 * )
 */
class NewClassProcessor extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The course notification helper service.
   *
   * @var \Drupal\ilr_campaigns\CourseNotificationHelper
   */
  protected $courseNotificationHelper;

  /**
   * Constructs a NewClassProcessor queue object.
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
    // @todo Consider confirming that the data is a node:class entity.
    $class = $data;

    if ($class->field_course->isEmpty()) {
      // Throw an exception to log the missing course. This will re-queue it,
      // but shouldn't hold up the rest of the queue.
      throw new \Exception('Class is missing a course. Class nid ' . $class->id());
    }

    $course = $class->field_course->entity;

    // Check if this Class or the related Course are unpublished.
    if (!$class->isPublished() || !$course->isPublished()) {
      // If class node create date is older than 28 days, allow the item to be
      // removed from the queue.
      if (time() - $class->created->value() > 60 * 60 * 24 * 28) {
        return;
      }

      // Re-queue this class in case it or the class is eventually published in
      // the near future.
      throw new \Exception('Course or class is unpublished. Class nid ' . $class->id());
    }

    $this->courseNotificationHelper->createClassNotification($class);
  }

}
