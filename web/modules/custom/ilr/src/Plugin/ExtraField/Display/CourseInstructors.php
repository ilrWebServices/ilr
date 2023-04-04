<?php

namespace Drupal\ilr\Plugin\ExtraField\Display;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\extra_field\Plugin\ExtraFieldDisplayBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Course Instructors extra field display.
 *
 * @ExtraFieldDisplay(
 *   id = "course_instructors",
 *   label = @Translation("Course instructors"),
 *   bundles = {
 *     "node.course",
 *   },
 *   visible = true
 * )
 */
class CourseInstructors extends ExtraFieldDisplayBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new CourseInstructors instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $request_stack
   *   The request stack object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function view(ContentEntityInterface $node) {
    $build = [];
    $node_storage = $this->entityTypeManager->getStorage('node');

    // Get participant nodes for this course. These are link to the actual
    // instructors via the `field_instructor` reference.
    $query = $node_storage->getQuery();
    $query->condition('type', 'participant');
    $query->condition('status', 1);
    $query->condition('field_class.entity:node.field_course.target_id', $node->id());
    $query->condition('field_class.entity:node.field_date_end', date('Y-m-d'), '>');
    $participant_nids = $query->execute();

    if (empty($participant_nids)) {
      return $build;
    }

    $participant_nodes = $node_storage->loadMultiple($participant_nids);
    $instructors = [];
    $view_builder = $this->entityTypeManager->getViewBuilder('node');

    foreach ($participant_nodes as $participant_node) {
      $instructor = $participant_node->field_instructor->entity;
      if ($instructor && $instructor->isPublished()) {
        $instructors[$participant_node->id()] = $view_builder->view($participant_node, 'mini');
      }
    }

    $build['ilr_course_instructors'] = [
      '#theme' => 'ilr_course_instructors_block',
      '#label' => $this->formatPlural(count($instructors), 'Instructor', 'Instructors'),
      '#instructors' => $instructors,
      '#cache' => [
        'tags' => ['node_list:participant', 'node_list:class'],
      ],
    ];

    return $build;
  }

}
