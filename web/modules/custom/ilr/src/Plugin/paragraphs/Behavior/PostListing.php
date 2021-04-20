<?php

namespace Drupal\ilr\Plugin\paragraphs\Behavior;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Query\PagerSelectExtender;

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
   * Count threshold before a pager is required.
   *
   * @var integer
   */
  protected $pagerThreshold = 51;

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
    // There is no #parents key in $form, but this may be OK hardcoded.
    $parents = $form['#parents'];
    $parents_input_name = array_shift($parents);
    $parents_input_name .= '[' . implode('][', $parents) . ']';

    $form['post_categories'] = [
      '#type' => 'select',
      '#title' => $this->t('Category'),
      '#description' => $this->t('Choose a category to filter the listing.'),
      '#options' => $this->getCategoryTermOptions($paragraph),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'post_categories'),
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

    $form['post_tags'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Tag(s)'),
      '#description' => $this->t('Optionally choose one or more tags to filter the listing.'),
      '#options' => $this->getTagTermOptions($paragraph),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'post_tags'),
    ];

    $form['count'] = [
      '#type' => 'number',
      '#required' => TRUE,
      '#title' => $this->t('Number of posts'),
      '#description' => $this->t('If using a pager, this is the number of posts per page.'),
      '#min' => 1,
      '#max' => 102,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'count') ?? 3,
    ];

    $form['use_pager'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use pager'),
      '#description' => $this->t('If the total number of posts exceeds the value above, a pager will appear below the listing.'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'use_pager'),
    ];

    return $form;
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
  }

  /**
   * {@inheritdoc}
   */
  public function submitBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    if (empty($form_state->getValue('post_categories'))) {
      $form_state->setValue('negate_category', FALSE);
    }

    parent::submitBehaviorForm($paragraph, $form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    $paragraph = $variables['paragraph'];
    $collection = $paragraph->field_collection->entity;
    $category_operator = $paragraph->getBehaviorSetting($this->getPluginId(), 'negate_category') ? '<>' : '=';

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

    $pending_items = \Drupal::database()
      ->select('collection_item__attributes', 'cia')
      ->fields('cia', ['entity_id'])
      ->condition('cia.bundle', 'blog')
      ->condition('cia.attributes_key', 'collection-request-uid');

    $query = $collection_item_storage->getQuery();
    $query->condition('id', $pending_items, 'NOT IN');
    $query->condition('collection', $collection->id());
    $query->condition('type', 'blog');
    $query->condition('item.entity:node.status', 1);
    $query->condition('item.entity:node.type', ['post', 'media_mention', 'post_experience_report'], 'IN');
    $query->sort('item.entity:node.field_published_date', 'DESC');

    // Add a dedupe tag to remove duplicates in similar post_listings. See
    // ilr_query_alter().
    $query->addTag($dedupe_group);

    if ($category_term_id = $paragraph->getBehaviorSetting($this->getPluginId(), 'post_categories')) {
      $category_group = $query->andConditionGroup();
      $category_group->condition('field_blog_categories', $category_term_id, $category_operator);
      $query->condition($category_group);
    }

    if ($tags_terms = $paragraph->getBehaviorSetting($this->getPluginId(), 'post_tags')) {
      foreach ($tags_terms as $tags_term_id) {
        $tags_group = $query->andConditionGroup();
        $tags_group->condition('field_blog_tags', $tags_term_id);
        $query->condition($tags_group);
      }
    }

    $list_style = $paragraph->getBehaviorSetting('list_styles', 'list_style') ?? 'grid';

    // Two of the grid list styles require the posts to have images.
    if (in_array($list_style, ['grid', 'grid-featured'])) {
      $query->condition('item.entity:node.field_representative_image', '', '<>');
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

    $post_count = 0;
    foreach ($collection_item_storage->loadMultiple($result) as $collection_item) {
      $post_count++;
      $rendered_entity = $view_builder->view($collection_item->item->entity, $this->getViewModeForListStyle($paragraph, $list_style, $post_count));
      $rendered_entity['#collection_item'] = $collection_item;
      $rendered_entity['#cache']['contexts'][] = 'url';
      $posts[] = $rendered_entity;
    }

    $variables['content']['field_collection']['#printed'] = TRUE;
    $variables['content']['post_listing'] = [
      'items' => $posts,
      '#cache' => [
        'tags' => $cache_tags,
      ],
    ];

    // QueryBase::pager(), used above, sets the pager element and _then_
    // increments PagerSelectExtender::$maxElement. So we subtract one from
    // PagerSelectExtender::$maxElement to get the last used pager element
    // number.
    if ($paragraph->getBehaviorSetting($this->getPluginId(), 'use_pager')) {
      $variables['content']['pager'] = [
        '#type' => 'pager',
        '#element' => PagerSelectExtender::$maxElement - 1,
        '#parameters' => ['post_listing' => PagerSelectExtender::$maxElement - 1],
        '#attached' => [
          'library' => ['ilr/ilr_pager'],
        ],
      ];
    }
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
   * @param int $post_number
   *   The order placement of the post in the listing.
   *
   * @return string
   *   A node view mode.
   */
  protected function getViewModeForListStyle(Paragraph $paragraph, $list_style, $post_number) {
    $view_mode = 'teaser';

    if ($list_styles_plugin = $paragraph->type->entity->getBehaviorPlugin('list_styles')) {
      $view_mode = $list_styles_plugin->getViewModeForListStyle($list_style, $post_number);
    }

    return $view_mode;
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

    $negated = $paragraph->getBehaviorSetting($this->getPluginId(), 'negate_category');
    $category_name = $selected_category_id ? $selected_category->label() : $this->t('All');

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
   * @param \Drupal\paragraphs\Entity\Paragraph $paragraph
   *   The paragraph entity.
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
      $collection_items = $collection->findItemsByAttribute('blog_taxonomy_categories', 'blog_' . $collection->id() . '_categories');
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
   * @param \Drupal\paragraphs\Entity\Paragraph $paragraph
   *   The paragraph entity.
   *
   * @return array
   *   List of term labels keyed by tid.
   */
  protected function getTagTermOptions(Paragraph $paragraph) {
    $collection = $paragraph->field_collection->entity;
    $options = [];

    if ($collection) {
      $collection_items = $collection->findItemsByAttribute('blog_taxonomy_tags', 'blog_' . $collection->id() . '_tags');
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
