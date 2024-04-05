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
   *
   * @var array
   */
  protected $listStyles = [
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
      '#title' => $this->t('Number of Stories'),
      '#description' => $this->t('Leave blank for all Stories.'),
      '#min' => 1,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'count'),
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
    $dedupe_group = 'dedupe:collection_item_field_data.id:collection_' . $collection->id();

    $query = $collection_item_storage->getQuery();
    $query->accessCheck(TRUE);
    $query->condition('collection', $collection->id());
    $query->condition('type', 'publication_issue');
    $query->condition('item.entity:node.status', 1);
    $query->condition('item.entity:node.type', 'story');
    // Add a dedupe tag to remove duplicates in similar story listings. See
    // ilr_query_alter().
    $query->addTag($dedupe_group);

    $list_style = $paragraph->getBehaviorSetting('list_styles', 'list_style');

    if ($limit = $paragraph->getBehaviorSetting($this->getPluginId(), 'count')) {
      $query->range(0, $limit);
    }

    $query->sort('weight');
    $query->sort('changed', 'DESC');
    $result = $query->execute();
    $story_count = 0;

    foreach ($collection_item_storage->loadMultiple($result) as $collection_item) {
      $story_count++;
      $rendered_entity = $view_builder->view($collection_item->item->entity, $this->getViewModeForListStyle($paragraph, $list_style));
      $rendered_entity['#collection_item'] = $collection_item;
      $rendered_entity['#cache']['contexts'][] = 'url';
      $stories[] = $rendered_entity;
    }

    $variables['content']['field_collection']['#printed'] = TRUE;
    $variables['content']['story_listing'] = [
      'items' => $stories,
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
   *   One of the list style machine names from this::listStyles.
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
    $category_labels = [];
    $tags_labels = [];

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
    return $paragraphs_type->id() === 'collection_listing_publication';
  }

}
