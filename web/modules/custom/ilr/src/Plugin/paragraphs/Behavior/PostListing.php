<?php

namespace Drupal\ilr\Plugin\paragraphs\Behavior;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\extended_post\ExtendedPostManager;
use Drupal\Core\Pager\PagerManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Post Listing plugin.
 *
 * @ParagraphsBehavior(
 *   id = "post_listing",
 *   label = @Translation("Post listing"),
 *   description = @Translation("Configure post listing settings."),
 *   weight = 1
 * )
 */
class PostListing extends ParagraphsBehaviorBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Post types from the extended post manager.
   *
   * @var array
   */
  protected $postTypes = [];

  /**
   * The pager manager.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * Count threshold before a pager is required.
   *
   * @var integer
   */
  protected $pagerThreshold = 51;

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
   * @param \Drupal\extended_post\ExtendedPostManager $extended_post_manager
   *   The extended post manager service.
   * @param \Drupal\Core\Pager\PagerManagerInterface $pager_manager
   *   The pager manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager, ExtendedPostManager $extended_post_manager, PagerManagerInterface $pager_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_field_manager);
    $this->entityTypeManager = $entity_type_manager;
    $this->postTypes = $extended_post_manager->getPostTypesWithLabels();
    $this->pagerManager = $pager_manager;
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
      $container->get('extended_post.manager'),
      $container->get('pager.manager')
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

    $collection_options = [];
    $default_collection_id = $paragraph->getBehaviorSetting($this->getPluginId(), 'collection');

    foreach ($this->entityTypeManager->getStorage('collection')->loadByProperties(['status' => 1]) as $collection) {
      $is_blog = (bool) $collection->type->entity->getThirdPartySetting('collection_blogs', 'contains_blogs');

      if ($is_blog) {
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
        'callback' => [$this, 'blogTermFieldsUpdate'],
        'event' => 'change',
        'wrapper' => 'edit-blog-terms',
      ],
    ];

    $form['post_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Post type(s)'),
      '#options' => $this->postTypes,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'post_types') ?? array_keys($this->postTypes),
    ];

    $default_categories = [];
    $default_tags = [];

    if ($default_collection_id) {
      $default_collection = $this->entityTypeManager->getStorage('collection')->load($default_collection_id);
      $default_categories = $this->getCategoryTermOptions($default_collection);
      $default_tags = $this->getTagTermOptions($default_collection);
    }

    $form['blog_terms'] = [
      '#type' => 'container',
      '#prefix' => '<div id="edit-blog-terms">',
      '#suffix' => '</div>',
    ];

    $form['blog_terms']['post_categories'] = [
      '#type' => 'select',
      '#title' => $this->t('Category'),
      '#description' => $this->t('Choose a category to filter the listing.'),
      '#options' => $default_categories,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), ['blog_terms', 'post_categories']),
      '#validated' => 'true',
    ];

    $form['negate_category'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('If a category is selected, negate it.'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'negate_category'),
      '#states' => [
        'invisible' => [
          ':input[name="' . $parents_input_name . '[post_categories]"]' => [
            ['value' => ''],
          ],
        ],
      ],
    ];

    $form['blog_terms']['post_tags'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Tag(s)'),
      '#description' => $this->t('Optionally choose one or more tags to filter the listing.'),
      '#options' => $default_tags,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), ['blog_terms', 'post_tags']) ?? [],
      '#validated' => 'true',
    ];

    $form['count'] = [
      '#type' => 'number',
      '#required' => TRUE,
      '#title' => $this->t('Number of posts'),
      '#description' => $this->t('If using a pager, this is the number of posts per page.'),
      '#min' => 1,
      '#max' => 150,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'count') ?? 3,
    ];

    $form['use_pager'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use pager'),
      '#description' => $this->t('If the total number of posts exceeds the value above, a pager will appear below the listing.'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'use_pager'),
    ];

    $form['ignore_sticky'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ignore sticky sorting'),
      '#description' => $this->t('If any posts in the listing are marked "Sticky at the top of lists", ignore that and sort by published date as usual.'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'ignore_sticky'),
    ];

    return $form;
  }

  /**
   * Callback for Collection select change event.
   */
  public function blogTermFieldsUpdate(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $collection_id = $form_state->getValue($trigger['#parents']);
    $collection = $this->entityTypeManager->getStorage('collection')->load($collection_id);
    $behavior_form = NestedArray::getValue($form, array_slice($trigger['#array_parents'], 0, -1));
    $behavior_form['blog_terms']['post_categories']['#options'] = $this->getCategoryTermOptions($collection);
    $behavior_form['blog_terms']['post_tags']['#options'] = $this->getTagTermOptions($collection);
    return $behavior_form['blog_terms'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    if (!$form_state->getValue('use_pager') && $form_state->getValue('count') > $this->pagerThreshold) {
      $form_state->setError($form['use_pager'], $this->t('A pager is required when number of posts is greater than %threshold.', [
        '%threshold' => $this->pagerThreshold,
      ]));
    }
    // Ensure that there is at least one post type selected.
    if (empty(array_filter($form_state->getValue('post_types')))) {
      $form_state->setError($form['post_types'], $this->t('Please choose at least one post type to display.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    if (empty($form_state->getValue(['blog_terms','post_categories']))) {
      $form_state->setValue('negate_category', FALSE);
    }

    parent::submitBehaviorForm($paragraph, $form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraph, EntityViewDisplayInterface $display, $view_mode) {
    $collection_id = $paragraph->getBehaviorSetting($this->getPluginId(), 'collection');

    // Ensure there is a collection id. At times this will be null, such as when editing a layout.
    if (!$collection_id) {
      return;
    }

    $collection = $this->entityTypeManager->getStorage('collection')->load($collection_id);
    $category_operator = $paragraph->getBehaviorSetting($this->getPluginId(), 'negate_category') ? '<>' : '=';
    $post_types = $paragraph->getBehaviorSetting($this->getPluginId(), 'post_types') ?? array_keys($this->postTypes);

    // If the collection was deleted, return nothing to prevent errors.
    if ($collection === NULL) {
      return;
    }

    if (empty($post_types)) {
      return;
    }

    $collection_item_storage = $this->entityTypeManager->getStorage('collection_item');
    $view_builder = $this->entityTypeManager->getViewBuilder('node');
    $cache_tags = $collection->getCacheTags();
    $cache_tags[] = 'node_list';
    // $cache_tags[] = 'node_list:post'; // 8.9 and above
    $posts = [];
    $dedupe_group = 'dedupe:collection_item_field_data.id:collection_' . $collection->id();

    $pending_items = \Drupal::database()
      ->select('collection_item__attributes', 'cia')
      ->fields('cia', ['entity_id'])
      ->condition('cia.bundle', 'blog')
      ->condition('cia.attributes_key', 'collection-request-uid');

    $query = $collection_item_storage->getQuery();
    $query->accessCheck(TRUE);
    $query->condition('id', $pending_items, 'NOT IN');
    $query->condition('collection', $collection->id());
    $query->condition('type', 'blog');
    $query->condition('item.entity:node.status', 1);
    $query->condition('item.entity:node.type', $post_types, 'IN');

    if (!$paragraph->getBehaviorSetting($this->getPluginId(), 'ignore_sticky')) {
      $query->sort('sticky', 'DESC');
    }

    $query->sort('item.entity:node.field_published_date', 'DESC');
    $query->sort('item.entity:node.created', 'DESC');

    // Add a dedupe tag to remove duplicates in similar post_listings. See
    // ilr_query_alter().
    $query->addTag($dedupe_group);

    if ($category_term_id = $paragraph->getBehaviorSetting($this->getPluginId(), ['blog_terms', 'post_categories'])) {
      $category_group = $query->andConditionGroup();
      $category_group->condition('field_blog_categories', $category_term_id, $category_operator);
      $query->condition($category_group);
    }

    if ($tags_terms = $paragraph->getBehaviorSetting($this->getPluginId(), ['blog_terms', 'post_tags'])) {
      foreach ($tags_terms as $tags_term_id) {
        $tags_group = $query->andConditionGroup();
        $tags_group->condition('field_blog_tags', $tags_term_id);
        $query->condition($tags_group);
      }
    }

    $list_style = $paragraph->getBehaviorSetting('list_styles', 'list_style') ?? 'grid';

    // Two of the grid list styles require the posts to have images.
    if (in_array($list_style, ['grid', 'grid-featured'])) {
      $has_image_group = $query->orConditionGroup();
      $has_image_group->condition('item.entity:node.field_representative_image', '', '<>');
      $has_image_group->condition('item.entity:node.field_video', '', '<>');
      $query->condition($has_image_group);
    }

    // Non-list styles require posts to have body text and/or summaries.
    if (!preg_match('/^list-/', $list_style)) {
      $has_summary_group = $query->orConditionGroup();
      $has_summary_group->condition('item.entity:node.body', '', '<>');
      $has_summary_group->condition('item.entity:node.body.summary', '', '<>');
      $query->condition($has_summary_group);
    }

    if ($limit = $paragraph->getBehaviorSetting($this->getPluginId(), 'count')) {
      if ($paragraph->getBehaviorSetting($this->getPluginId(), 'use_pager')) {
        $query->pager($limit);
      }
      else {
        $query->range(0, $limit);
      }
    }

    $result = $query->execute();

    foreach ($collection_item_storage->loadMultiple($result) as $collection_item) {
      $rendered_entity = $view_builder->view($collection_item->item->entity, $this->getViewModeForListStyle($paragraph, $list_style));
      $rendered_entity['#collection_item'] = $collection_item;
      $rendered_entity['#cache']['contexts'][] = 'url';
      $posts[] = $rendered_entity;
    }

    $build['listing'] = [
      'items' => $posts,
      '#cache' => [
        'tags' => $cache_tags,
      ],
    ];

    // QueryBase::pager(), used above, sets the pager element. The pager manager
    // can now get that element value.
    if ($paragraph->getBehaviorSetting($this->getPluginId(), 'use_pager')) {
      $element_id = $this->pagerManager->getMaxPagerElementId();
      $build['pager'] = [
        '#type' => 'pager',
        '#element' => $element_id,
        '#parameters' => ['post_listing' => $element_id],
        '#attached' => [
          'library' => ['ilr/ilr_pager'],
        ],
      ];
    }
  }

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
    $view_mode = 'teaser';

    if ($list_styles_plugin = $paragraph->type->entity->getBehaviorPlugin('list_styles')) {
      $view_mode = $list_styles_plugin->getViewModeForListStyle($list_style);
    }

    return $view_mode;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(Paragraph $paragraph) {
    $summary = [];
    $tags_labels = [];
    $post_types = ['All'];
    $collection_id = $paragraph->getBehaviorSetting($this->getPluginId(), 'collection');

    // Ensure that the post listing has a post_type behavior setting.
    if ($selected_post_types = $paragraph->getBehaviorSetting($this->getPluginId(), 'post_types')) {
      $selected_post_types = array_filter($selected_post_types);

      if (array_keys($selected_post_types) !== array_keys($this->postTypes)) {
        $post_types = [];

        foreach ($selected_post_types as $machine_name) {
          $post_types[] = $this->postTypes[$machine_name] ?? $machine_name;
        }
      }
    }

    if ($selected_category_id = $paragraph->getBehaviorSetting($this->getPluginId(), ['blog_terms', 'post_categories'])) {
      $selected_category = $this->entityTypeManager->getStorage('taxonomy_term')->load($selected_category_id);
    }

    if ($selected_tags_ids = $paragraph->getBehaviorSetting($this->getPluginId(), 'post_tags')) {
      $selected_tags = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($selected_tags_ids);

      foreach ($selected_tags as $selected_tag) {
        $tags_labels[] = $selected_tag->label();
      }
    }

    if ($collection_id) {
      $collection = $this->entityTypeManager->getStorage('collection')->load($collection_id);
      $summary[] = [
        'label' => 'Collection',
        'value' => $collection->label(),
      ];
    }

    $negated = $paragraph->getBehaviorSetting($this->getPluginId(), 'negate_category');
    $category_name = $selected_category_id ? $selected_category->label() : $this->t('All');

    $summary[] = [
      'label' => 'Types',
      'value' => implode(', ', $post_types),
    ];

    $summary[] = [
      'label' => 'Category',
      'value' => ($negated ? $this->t('All except') . ' ' : '') . $category_name,
    ];

    $summary[] = [
      'label' => 'Tags',
      'value' => $tags_labels ? implode(', ', $tags_labels) : 'All',
    ];

    $summary[] = [
      'label' => 'Show',
      'value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'count') ?? 'All',
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
   * {@inheritdoc}
   *
   * This behavior is only applicable to paragraphs that have a single
   * `collection` entity reference field.
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    return $paragraphs_type->id() === 'simple_collection_listing';
  }

  /**
   * Get category term options collection set on the paragraph.
   *
   * @param \Drupal\collection\Entity\CollectionInterface $collection
   *   A collection entity.
   *
   * @return array
   *   List of term labels keyed by tid.
   */
  protected function getCategoryTermOptions(CollectionInterface $collection) {
    $options = [
      '' => '-- all --',
    ];

    $collection_items = $collection->findItemsByAttribute('blog_taxonomy_categories', 'blog_' . $collection->id() . '_categories');
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
   * Get tag term options collection set on the paragraph.
   *
   * @param \Drupal\collection\Entity\CollectionInterface $collection
   *   A collection entity.
   *
   * @return array
   *   List of term labels keyed by tid.
   */
  protected function getTagTermOptions(CollectionInterface $collection) {
    $options = [];
    $collection_items = $collection->findItemsByAttribute('blog_taxonomy_tags', 'blog_' . $collection->id() . '_tags');
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

}
