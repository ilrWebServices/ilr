<?php

namespace Drupal\ilr\Plugin\paragraphs\Behavior;

use Drupal\collection_projects\CollectionProjectsManager;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Project Listing plugin.
 *
 * @ParagraphsBehavior(
 *   id = "project_listing",
 *   label = @Translation("Project listing"),
 *   description = @Translation("Configure project listing settings."),
 *   weight = 1
 * )
 */
class ProjectListing extends ParagraphsBehaviorBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The collection projects manager.
   *
   * @var \Drupal\collection_projects\CollectionProjectsManager
   */
  protected $collectionProjectsManager;

  /**
   * Creates a new PostListing behavior.
   *
   * @param array $configuration
   *   The configuration array.
   * @param string $plugin_id
   *   This plugin id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   Entity field manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\collection_projects\CollectionProjectsManager $projects_manager
   *   The collection projects manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager, CollectionProjectsManager $projects_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_field_manager);
    $this->entityTypeManager = $entity_type_manager;
    $this->collectionProjectsManager = $projects_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('collection_projects.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    // There is no #parents key in $form, but this may be OK hardcoded.
    $parents = $form['#parents'];
    $parents_input_name = array_shift($parents);
    $parents_input_name .= '[' . implode('][', $parents) . ']';

    $project_types = $this->collectionProjectsManager->getProjectTypesWithLabels();
    $collection_options = [];
    $default_collection_id = $paragraph->getBehaviorSetting($this->getPluginId(), 'collection');

    foreach ($this->entityTypeManager->getStorage('collection')->loadByProperties(['status' => 1]) as $collection) {
      if ($this->collectionProjectsManager->collectionCanContainProjects($collection)) {
        $collection_options[$collection->id()] = $collection->label();
      }
    }

    // Sort the collection options alphabetically.
    asort($collection_options);

    $form['collection'] = [
      '#type' => 'select',
      '#title' => $this->t('Collection'),
      '#options' => $collection_options,
      '#default_value' => $default_collection_id,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'focusAreasUpdate'],
        'event' => 'change',
        'wrapper' => 'edit-focus-area',
      ],
    ];

    $form['project_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Project type(s)'),
      '#options' => $project_types,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'project_types') ?? array_keys($project_types),
    ];

    $default_focus_areas = [];

    if ($default_collection_id) {
      $default_collection = $this->entityTypeManager->getStorage('collection')->load($default_collection_id);
      $default_focus_areas = $this->getFocusAreaTermOptions($default_collection);
    }

    $form['focus_areas'] = [
      '#type' => 'select',
      '#title' => $this->t('Focus area'),
      '#description' => $this->t('Choose a focus area to filter the listing.'),
      '#options' => $default_focus_areas,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'focus_areas'),
      '#prefix' => '<div id="edit-focus-area">',
      '#suffix' => '</div>',
      '#validated' => 'true',
    ];

    $form['ignore_sticky'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ignore sticky sorting'),
      '#description' => $this->t('If any projects in the listing are marked "Sticky at the top of lists", ignore that and sort alphabetically as usual.'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'ignore_sticky'),
    ];

    return $form;
  }

  /**
   * Callback for Collection select change event.
   */
  public function focusAreasUpdate(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $collection_id = $form_state->getValue($trigger['#parents']);
    $collection = $this->entityTypeManager->getStorage('collection')->load($collection_id);
    $behavior_form = NestedArray::getValue($form, array_slice($trigger['#array_parents'], 0, -1));
    $behavior_form['focus_areas']['#options'] = $this->getFocusAreaTermOptions($collection);
    return $behavior_form['focus_areas'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    // Ensure that there is at least one project type selected.
    if (empty(array_filter($form_state->getValue('project_types')))) {
      $form_state->setError($form['project_types'], $this->t('Please choose at least one project type to display.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    $paragraph = $variables['paragraph'];
    $collection_id = $paragraph->getBehaviorSetting($this->getPluginId(), 'collection');

    if (!$collection_id) {
      return;
    }

    $collection = $this->entityTypeManager->getStorage('collection')->load($collection_id);
    $project_types = $paragraph->getBehaviorSetting($this->getPluginId(), 'project_types') ?? array_keys($this->collectionProjectsManager->getProjectTypesWithLabels());

    // If the collection was deleted, return nothing to prevent errors.
    if ($collection === NULL) {
      return;
    }

    if (empty($project_types)) {
      return;
    }

    $collection_item_storage = $this->entityTypeManager->getStorage('collection_item');
    $view_builder = $this->entityTypeManager->getViewBuilder('node');
    $cache_tags = $collection->getCacheTags();

    foreach ($project_types as $project_type) {
      $cache_tags[] = 'node_list:' . $project_type;
    }

    $projects = [];

    $pending_items = \Drupal::database()
      ->select('collection_item__attributes', 'cia')
      ->fields('cia', ['entity_id'])
      ->condition('cia.bundle', 'project_item')
      ->condition('cia.attributes_key', 'collection-request-uid');

    $query = $collection_item_storage->getQuery();
    $query->accessCheck(TRUE);
    $query->condition('id', $pending_items, 'NOT IN');
    $query->condition('collection', $collection->id());
    $query->condition('type', 'project_item');
    $query->condition('item.entity:node.status', 1);
    $query->condition('item.entity:node.type', $project_types, 'IN');

    if (!$paragraph->getBehaviorSetting($this->getPluginId(), 'ignore_sticky')) {
      $query->sort('sticky', 'DESC');
    }

    $query->sort('weight', 'ASC');
    $query->sort('item.entity:node.title', 'ASC');

    if ($area_term_id = $paragraph->getBehaviorSetting($this->getPluginId(), 'focus_areas')) {
      $area_group = $query->andConditionGroup();
      $area_group->condition('field_focus_areas', $area_term_id);
      $query->condition($area_group);
    }

    $list_style = $paragraph->getBehaviorSetting('list_styles', 'list_style') ?? 'grid';

    if ($list_style === 'select-list') {
      $projects[] = [
        '#markup' => '<div class="project-list__trigger" aria-expanded="false" value="Expand Project List" aria-label="Expand Project List">▼</div><div class="cu-x-field field--title">' . $this->t('-- Choose a project --') . '</div>',
        '#attached' => [
          'library' => ['ilr/ilr_select_list'],
        ],
      ];
    }

    // Two of the grid list styles require the projects to have images.
    if (in_array($list_style, ['grid', 'grid-featured'])) {
      $has_image_group = $query->orConditionGroup();
      $has_image_group->condition('item.entity:node.field_representative_image', '', '<>');
      $query->condition($has_image_group);
    }

    // Non-list styles require projects to have body text and/or summaries.
    if (!preg_match('/^list-/', $list_style)) {
      $has_summary_group = $query->orConditionGroup();
      $has_summary_group->condition('item.entity:node.body', '', '<>');
      $has_summary_group->condition('item.entity:node.body.summary', '', '<>');
      $query->condition($has_summary_group);
    }

    $result = $query->execute();

    foreach ($collection_item_storage->loadMultiple($result) as $collection_item) {
      $rendered_entity = $view_builder->view($collection_item->item->entity, $this->getViewModeForListStyle($paragraph, $list_style));
      $rendered_entity['#collection_item'] = $collection_item;
      $rendered_entity['#cache']['contexts'][] = 'url';
      $projects[] = $rendered_entity;
    }

    $variables['content']['project_listing'] = [
      'items' => $projects,
      '#cache' => [
        'tags' => $cache_tags,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraphs_entity, EntityViewDisplayInterface $display, $view_mode) {}

  /**
   * Get a node view mode for a given list style.
   *
   * @param \Drupal\paragraphs\Entity\Paragraph $paragraph
   *   The paragraph entity.
   * @param string $list_style
   *   One of the list style machine names from this::list_styles.
   *
   * @return string
   *   A node view mode.
   */
  protected function getViewModeForListStyle(Paragraph $paragraph, $list_style) {
    if ($list_styles_plugin = $paragraph->type->entity->getBehaviorPlugin('list_styles')) {
      return $list_styles_plugin->getViewModeForListStyle($list_style);
    }

    return 'teaser';
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(Paragraph $paragraph) {
    $summary = [];
    $project_types = ['All'];
    $all_project_types = $this->collectionProjectsManager->getProjectTypesWithLabels();
    $collection_id = $paragraph->getBehaviorSetting($this->getPluginId(), 'collection');

    // Ensure that the project listing has a project_type behavior setting.
    if ($selected_project_types = $paragraph->getBehaviorSetting($this->getPluginId(), 'project_types')) {
      $selected_project_types = array_filter($selected_project_types);

      if (array_keys($selected_project_types) !== array_keys($all_project_types)) {
        $project_types = [];

        foreach ($selected_project_types as $machine_name) {
          $project_types[] = $all_project_types[$machine_name] ?? $machine_name;
        }
      }
    }

    if ($selected_area_id = $paragraph->getBehaviorSetting($this->getPluginId(), 'focus_areas')) {
      $selected_area = $this->entityTypeManager->getStorage('taxonomy_term')->load($selected_area_id);
    }

    $area_name = $selected_area_id ? $selected_area->label() : $this->t('All');

    if ($collection_id) {
      $collection = $this->entityTypeManager->getStorage('collection')->load($collection_id);
      $summary[] = [
        'label' => 'Collection',
        'value' => $collection->label(),
      ];
    }

    $summary[] = [
      'label' => 'Types',
      'value' => implode(', ', $project_types),
    ];

    $summary[] = [
      'label' => 'Focus area',
      'value' => $area_name,
    ];

    if ($paragraph->getBehaviorSetting($this->getPluginId(), 'ignore_sticky')) {
      $summary[] = [
        'label' => 'Ignore sticky sort',
        'value' => '✓',
      ];
    }

    return $summary;
  }

  /**
   * Get focus area term options for a given collection.
   *
   * @param \Drupal\collection\Entity\CollectionInterface $collection
   *   A collection entity.
   *
   * @return array
   *   List of term labels keyed by tid.
   */
  protected function getFocusAreaTermOptions($collection) {
    $options = [
      '' => '-- all --',
    ];

    $collection_items = $collection->findItemsByAttribute('project_taxonomy_focus_areas', "project_{$collection->id()}_focus_areas");
    /** @var \Drupal\taxonomy\TermStorage $term_manager */
    $term_manager = $this->entityTypeManager->getStorage('taxonomy_term');

    foreach ($collection_items as $collection_item) {
      $vocab = $collection_item->item->entity;
      $category_terms = $term_manager->loadTree($vocab->id(), 0, NULL, TRUE);

      foreach ($category_terms as $term) {
        if ($term->isPublished()) {
          $options[$term->id()] = $term->label();
        }
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    return $paragraphs_type->id() === 'project_listing';
  }

}
