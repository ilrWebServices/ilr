<?php

namespace Drupal\ilr\Plugin\paragraphs\Behavior;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;
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
   * The list style options.
   */
  protected $list_styles = [
    'grid' => 'Grid',
    'grid-compact' => 'Compact grid',
    'list-compact' => 'Compact list',
    'grid-featured' => 'Featured Grid',
  ];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_field.manager'));
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $form['post_categories'] = [
      '#type' => 'select',
      '#title' => $this->t('Category'),
      '#description' => $this->t('Choose a category to filter the listing.'),
      '#options' => $this->getCategoryTermOptions($paragraph),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'post_categories'),
    ];

    $form['post_tags'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Tag(s)'),
      '#description' => $this->t('Optionally choose one or more tags to filter the listing.'),
      '#options' => $this->getTagTermOptions($paragraph),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'post_tags'),
    ];

    $form['count'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of posts'),
      '#description' => $this->t('Leave blank for all posts.'),
      '#min' => 1,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'count'),
    ];

    $form['list_style'] = [
      '#type' => 'select',
      '#title' => $this->t('List style'),
      '#description' => $this->t('Grid and Feature Grid will only display posts that have images.'),
      '#options' => $this->list_styles,
      '#required' => TRUE,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'list_style'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    $paragraph = $variables['paragraph'];
    $collection = $paragraph->field_collection->entity;

    // If the collection was deleted, return nothing to prevent errors.
    if ($collection === NULL) {
      return;
    }

    $collection_item_storage = $this->entityTypeManager->getStorage('collection_item');
    $view_builder = $this->entityTypeManager->getViewBuilder('node');
    $cache_tags = $collection->getCacheTags();
    $cache_tags[] = 'node_list';
    // $cache_tags[] = 'node_list:post'; // 8.9 and above
    $posts = [];
    $dedupe_group = 'dedupe:collection_item.id:collection_' . $collection->id();

    $query = $collection_item_storage->getQuery();
    $query->condition('collection', $collection->id());
    $query->condition('type', 'blog');
    $query->condition('item.entity:node.status', 1);
    $query->condition('item.entity:node.type', ['post', 'media_mention'], 'IN');
    // Add a dedupe tag to remove duplicates in similar post_listings. See
    // ilr_query_alter().
    $query->addTag($dedupe_group);

    if ($category_term_id = $paragraph->getBehaviorSetting($this->getPluginId(), 'post_categories')) {
      $category_group = $query->andConditionGroup();
      $category_group->condition('field_blog_categories', $category_term_id);
      $query->condition($category_group);
    }

    if ($tags_terms = $paragraph->getBehaviorSetting($this->getPluginId(), 'post_tags')) {
      foreach ($tags_terms as $tags_term_id) {
        $tags_group = $query->andConditionGroup();
        $tags_group->condition('field_blog_tags', $tags_term_id);
        $query->condition($tags_group);
      }
    }

    $list_style = $paragraph->getBehaviorSetting($this->getPluginId(), 'list_style');

    // Two of the grid list styles require the posts to have images.
    if (in_array($list_style, ['grid', 'grid-featured'])) {
      $query->condition('item.entity:node.field_representative_image', '', '<>');
    }

    if ($limit = $paragraph->getBehaviorSetting($this->getPluginId(), 'count')) {
      $query->range(0, $limit);
    }

    $query->sort('item.entity:node.field_published_date', 'DESC');
    $result = $query->execute();

    $post_count = 0;
    foreach ($collection_item_storage->loadMultiple($result) as $collection_item) {
      $post_count++;
      $posts[] = $view_builder->view($collection_item->item->entity, $this->getViewModeForListStyle($list_style, $post_count));
    }

    $variables['content']['field_collection'] = [
      '#theme' => 'item_list__collection_listing',
      '#items' => $posts,
      '#attributes' => ['class' => array_merge(['collection-listing'], $this->getClassesForListStyle($list_style))],
      '#collection_listing' => TRUE,
      '#empty' => $this->t('Content coming soon.'),
      '#context' => ['paragraph' => $variables['paragraph']],
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
   * @param $list_style string
   *   One of the list style machine names from this::list_styles.
   *
   * @param $post_number int
   *   The order placement of the post in the listing.
   *
   * @return string
   *   A node view mode.
   */
  protected function getViewModeForListStyle($list_style, $post_number) {
    switch ($list_style) {
      case 'grid-compact':
        return 'teaser_compact';
      case 'list-compact':
        return 'mini';
      case 'grid-featured':
        return $post_number === 1 ? 'featured' : 'teaser';
      default:
        return 'teaser';
    }
  }

  /**
   * Get CSS classes for a given list style.
   *
   * @param $list_style string
   *   One of the list style machine names from this::list_styles.
   *
   * @return array
   *   An array of class names for the listing wrapper.
   */
  protected function getClassesForListStyle($list_style) {
    $classes = [];

    if (strpos($list_style, 'grid') === 0) {
      $classes[] = 'cu-grid';
      $classes[] = 'cu-grid--3col';
    }

    if ($list_style === 'grid-featured') {
      $classes[] = 'cu-grid--featured';
    }

    return $classes;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(Paragraph $paragraph) {
    $summary = [];
    $category_labels = [];
    $tags_labels = [];

    if ($selected_category_id = $paragraph->getBehaviorSetting($this->getPluginId(), 'post_categories')) {
      $selected_category = $this->entityTypeManager->getStorage('taxonomy_term')->load($selected_category_id);
    }

    if ($selected_tags_ids = $paragraph->getBehaviorSetting($this->getPluginId(), 'post_tags')) {
      $selected_tags = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($selected_tags_ids);

      foreach ($selected_tags as $selected_tag) {
        $tags_labels[] = $selected_tag->label();
      }
    }

    if ($paragraph->getBehaviorSetting($this->getPluginId(), 'list_style')) {
      $style = $this->list_styles[$paragraph->getBehaviorSetting($this->getPluginId(), 'list_style')];
    }
    else {
      $style = '';
    }

    $summary[] = [
      'label' => 'Category',
      'value' =>  $selected_category_id ? $selected_category->label() : 'All',
    ];

    $summary[] = [
      'label' => 'Tags',
      'value' =>  $tags_labels ? implode(', ', $tags_labels) : 'All',
    ];

    $summary[] = [
      'label' => 'Show',
      'value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'count') ?? 'All',
    ];

    $summary[] = [
      'label' => 'Style',
      'value' => $style,
    ];

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
   * @param Paragraph $paragraph
   *
   * @return array
   *   List of term labels keyed by tid.
   */
  protected function getCategoryTermOptions(Paragraph $paragraph) {
    $collection = $paragraph->field_collection->entity;
    $options = [
      '' => '-- all --',
    ];

    if ($collection) {
      $collection_items = $collection->findItemsByAttribute('blog_taxonomy_categories', TRUE);
      $term_manager = $this->entityTypeManager->getStorage('taxonomy_term');

      foreach ($collection_items as $collection_item) {
        $vocab = $collection_item->item->entity;
        $category_terms = $term_manager->loadTree($vocab->id(), 0, NULL, TRUE);

        foreach ($category_terms as $term) {
          $options[$term->id()] = $term->label();
        }
      }
    }

    return $options;
  }

  /**
   * Get tag term options collection set on the paragraph.
   *
   * @param Paragraph $paragraph
   *
   * @return array
   *   List of term labels keyed by tid.
   */
  protected function getTagTermOptions(Paragraph $paragraph) {
    $collection = $paragraph->field_collection->entity;
    $options = [];

    if ($collection) {
      $collection_items = $collection->findItemsByAttribute('blog_taxonomy_tags', TRUE);
      $term_manager = $this->entityTypeManager->getStorage('taxonomy_term');

      foreach ($collection_items as $collection_item) {
        $vocab = $collection_item->item->entity;
        $category_terms = $term_manager->loadTree($vocab->id(), 0, NULL, TRUE);

        foreach ($category_terms as $term) {
          $options[$term->id()] = $term->label();
        }
      }
    }

    return $options;
  }

}
