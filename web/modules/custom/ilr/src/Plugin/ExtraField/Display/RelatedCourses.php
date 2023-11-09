<?php

namespace Drupal\ilr\Plugin\ExtraField\Display;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\extra_field\Plugin\ExtraFieldDisplayBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Example Extra field Display.
 *
 * @ExtraFieldDisplay(
 *   id = "related_courses",
 *   label = @Translation("Related courses"),
 *   bundles = {
 *     "node.course",
 *   },
 *   visible = true
 * )
 */
class RelatedCourses extends ExtraFieldDisplayBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  const RELATED_COURSE_LENGTH = 3;

  /**
   * The node storage.
   *
   * @var \Drupal\node\NodeStorage
   */
  protected $nodeStorage;

  /**
   * The entity view builder.
   *
   * @var \Drupal\Core\Entity\EntityViewBuilderInterface
   */
  protected $viewBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->nodeStorage = $container->get('entity_type.manager')->getStorage('node');
    $instance->viewBuilder = $container->get('entity_type.manager')->getViewBuilder('node');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function view(ContentEntityInterface $entity) {
    $elements = [];

    // Topics is a required field, but sometimes courses created via salesforce
    // are missing one.
    if ($entity->field_topics->isEmpty()) {
      return $elements;
    }

    // Get the 'primary' topic term id for the current course node. The term
    // reference with the delta 0, e.g. the first one in the multivalue field,
    // is considered the primary.
    $primary_tid = $entity->field_topics->get(0)->target_id;

    if (!$primary_tid) {
      return $elements;
    }

    $related_node_ids = $this->nodeStorage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'course')
      ->condition('status', 1)
      ->condition('nid', $entity->id(), '!=')
      ->condition('field_topics.0.target_id', $primary_tid)
      ->execute();

    $related_nodes = $this->nodeStorage->loadMultiple(array_values($related_node_ids));

    // Filter out course nodes with no upcoming classes.
    $related_nodes_upcoming = array_filter($related_nodes, function ($node) {
      return (bool) $node->classes->count();
    });

    // Sort course nodes by first upcoming class date.
    uasort($related_nodes_upcoming, function ($a, $b) {
      $class_a_start_date = new DrupalDateTime($a->classes->first()->entity->field_date_start->value);
      $class_b_start_date = new DrupalDateTime($b->classes->first()->entity->field_date_start->value);
      return $class_a_start_date->getPhpDateTime() > $class_b_start_date->getPhpDateTime() ? 1 : -1;
    });

    $items = [];

    foreach ($related_nodes_upcoming as $node) {
      $items[] = $this->viewBuilder->view($node, 'teaser');

      if (count($items) === self::RELATED_COURSE_LENGTH) {
        break;
      }
    }

    $elements['related_courses'] = [
      '#theme' => 'item_list__related_courses',
      '#title' => $this->t('Similar &amp; Related'),
      '#items' => $items,
      '#attributes' => ['class' => 'related-courses'],
      '#context' => ['node' => $entity],
      '#cache' => [
        'tags' => ['node_list:course', 'node_list:class'],
      ],
    ];

    return $elements;
  }

}
