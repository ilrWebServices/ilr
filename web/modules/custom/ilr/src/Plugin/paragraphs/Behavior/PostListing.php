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

    foreach ($paragraph->field_categories as $value) {
      $group = $query->andConditionGroup();
      $group->condition('item.entity:node.field_categories', $value->target_id);
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
    $summary[] = [
      'label' => 'Posts',
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
}
