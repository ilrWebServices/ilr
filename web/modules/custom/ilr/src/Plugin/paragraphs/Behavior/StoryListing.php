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
 * Provides a Story Listing plugin.
 *
 * @ParagraphsBehavior(
 *   id = "story_listing",
 *   label = @Translation("Story listing"),
 *   description = @Translation("Configure story listing settings."),
 *   weight = 1
 * )
 */
class StoryListing extends ParagraphsBehaviorBase {

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
    'banner' => 'Banner',
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
    $form['count'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of storys'),
      '#description' => $this->t('Leave blank for all storys.'),
      '#min' => 1,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'count'),
    ];

    $form['list_style'] = [
      '#type' => 'select',
      '#title' => $this->t('List style'),
      '#description' => $this->t('Grid and Feature Grid will only display storys that have images.'),
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
    // $cache_tags[] = 'node_list:story'; // 8.9 and above
    $stories = [];
    $dedupe_group = 'dedupe:collection_item.id:collection_' . $collection->id();

    $query = $collection_item_storage->getQuery();
    $query->condition('collection', $collection->id());
    $query->condition('type', 'publication_issue');
    $query->condition('item.entity:node.status', 1);
    $query->condition('item.entity:node.type', 'story');
    // Add a dedupe tag to remove duplicates in similar story listings. See
    // ilr_query_alter().
    $query->addTag($dedupe_group);

    $list_style = $paragraph->getBehaviorSetting($this->getPluginId(), 'list_style');

    if ($limit = $paragraph->getBehaviorSetting($this->getPluginId(), 'count')) {
      $query->range(0, $limit);
    }

    $query->sort('weight');
    $result = $query->execute();
    $story_count = 0;

    foreach ($collection_item_storage->loadMultiple($result) as $collection_item) {
      $story_count++;
      $stories[] = $view_builder->view($collection_item->item->entity, $this->getViewModeForListStyle($list_style, $story_count));
    }

    $variables['content']['field_collection'] = [
      '#theme' => 'item_list__collection_listing',
      '#items' => $stories,
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
   * @param $story_number int
   *   The order placement of the story in the listing.
   *
   * @return string
   *   A node view mode.
   */
  protected function getViewModeForListStyle($list_style, $story_number) {
    switch ($list_style) {
      case 'banner':
        return 'banner';

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

    return $classes;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(Paragraph $paragraph) {
    $summary = [];
    $category_labels = [];
    $tags_labels = [];

    if ($paragraph->getBehaviorSetting($this->getPluginId(), 'list_style')) {
      $style = $this->list_styles[$paragraph->getBehaviorSetting($this->getPluginId(), 'list_style')];
    }
    else {
      $style = '';
    }

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
    return $paragraphs_type->id() === 'collection_listing_publication';
  }

}
