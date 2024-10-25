<?php

namespace Drupal\ilr\Plugin\ExtraField\Display;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\extra_field\Plugin\ExtraFieldDisplayBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Extra field Display for courses for professional education topics.
 *
 * @ExtraFieldDisplay(
 *   id = "topic_courses",
 *   label = @Translation("Topic courses"),
 *   bundles = {
 *     "taxonomy_term.topics",
 *   },
 *   visible = true
 * )
 */
class TopicCourses extends ExtraFieldDisplayBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  public function __construct(
    array $configuration,
    protected string $plugin_id,
    protected mixed $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RequestStack $requestStack
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
      $container->get('entity_type.manager'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function view(ContentEntityInterface $entity) {
    $elements = [];

    if (!$term = $this->requestStack->getCurrentRequest()->get('taxonomy_term')) {
      return $elements;
    }

    $node_storage = $this->entityTypeManager->getStorage('node');
    $node_view_builder = $this->entityTypeManager->getViewBuilder('node');
    $class_node_query = $node_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'class')
      ->condition('status', 1)
      ->condition('field_course.entity.status', 1)
      // ->condition('field_topics.0.target_id', $term->id()) // Limit the courses to the selected term as the primary.
      ->condition('field_course.entity.field_topics', $term->id())
      ->sort('field_date_start', 'ASC');

    // Limit the query to classes that start or can still be registered for in
    // the future. We use `UTC` because that's how it's stored in the database.
    $midnight_tonight = new DrupalDateTime('today 23:59');
    $current_utc = new DrupalDateTime('now', 'UTC');
    $upcoming_group = $class_node_query->orConditionGroup()
      ->condition('field_date_start', $midnight_tonight->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT), '>=')
      ->condition('field_close_registration', $current_utc->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT), '>=');
    $class_node_query->condition($upcoming_group);
    $class_node_ids = $class_node_query->execute();
    $class_nodes = $node_storage->loadMultiple(array_values($class_node_ids));
    $course_nodes = [];

    foreach ($class_nodes as $class_node) {
      // We'll get lots of the classes for the same course, but this will just
      // keep overwriting the key until the last duplicate.
      $course_nodes[$class_node->field_course->entity->id()] = $node_view_builder->view($class_node->field_course->entity, 'teaser');
    }

    $elements['topic_courses'] = [
      '#theme' => 'item_list__topic_courses',
      '#title' => $this->t('Upcoming courses'),
      '#items' => [],
      '#attributes' => ['class' => 'topic-courses'],
      '#context' => ['term' => $term],
      '#cache' => [
        'tags' => ['node_list:course', 'node_list:class'],
      ],
    ];

    foreach ($course_nodes as $course_node) {
      $elements['topic_courses']['#items'][] = $course_node;
    }

    return $elements;
  }

}
