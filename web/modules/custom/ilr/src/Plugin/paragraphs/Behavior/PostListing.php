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
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Categories'),
      '#description' => $this->t('Choose one or more categories to filter the listing. Posts with all of the categories will be returned.'),
      '#target_type' => 'taxonomy_term',
      '#tags' => TRUE,
      '#selection_settings' => [
        'target_bundles' => ['post_categories'],
      ],
      '#default_value' => $this->getCategoryTermsFromBehavior($paragraph),
    ];

    $form['count'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of posts'),
      '#description' => $this->t('Leave blank for all posts.'),
      '#min' => 1,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'count'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    $collection_item_storage = $this->entityTypeManager->getStorage('collection_item');
    $paragraph = $variables['paragraph'];
    $collection = $variables['paragraph']->field_collection->entity;
    $view_builder = $this->entityTypeManager->getViewBuilder('node');
    $cache_tags = $collection->getCacheTags();
    $cache_tags[] = 'node_list';
    // $cache_tags[] = 'node_list:post'; // 8.9 and above
    $posts = [];

    $query = $collection_item_storage->getQuery();
    $query->condition('collection', $collection->id());
    $query->condition('item.entity:node.status', 1);
    $query->condition('item.entity:node.type', 'post');

    foreach ($this->getCategoryTermsFromBehavior($paragraph) as $category_term) {
      $group = $query->andConditionGroup();
      $group->condition('item.entity:node.field_categories', $category_term->id());
      $query->condition($group);
    }

    if ($limit = $paragraph->getBehaviorSetting($this->getPluginId(), 'count')) {
      $query->range(0, $limit);
    }

    $query->sort('item.entity:node.field_published_date', 'DESC');
    $result = $query->execute();

    foreach ($collection_item_storage->loadMultiple($result) as $collection_item) {
      $posts[] = $view_builder->view($collection_item->item->entity, 'teaser');
    }

    $variables['content']['field_collection'] = [
      '#theme' => 'item_list__collection_listing',
      '#items' => $posts,
      '#attributes' => ['class' => 'collection-listing'],
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
   * {@inheritdoc}
   */
  public function settingsSummary(Paragraph $paragraph) {
    $summary = [];
    $category_labels = [];

    if ($categories = $this->getCategoryTermsFromBehavior($paragraph)) {
      foreach ($categories as $category_term) {
        $category_labels[] = $category_term->label();
      }
    }

    $summary[] = [
      'label' => 'Categories',
      'value' =>  ($category_labels) ? implode(', ', $category_labels) : 'All',
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
   * Get category terms from the behavior setting on the paragraph.
   *
   * @param Paragraph $paragraph
   *
   * @return array
   *   List of TermInterface objects
   */
  protected function getCategoryTermsFromBehavior(Paragraph $paragraph) {
    $categories = [];
    $category_settings_tids = [];
    $category_settings = $paragraph->getBehaviorSetting($this->getPluginId(), 'post_categories') ?? [];

    foreach ($category_settings as $value) {
      $category_settings_tids[] = $value['target_id'];
    }

    if ($category_settings_tids) {
      $categories = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($category_settings_tids);
    }

    return $categories;
  }
}
