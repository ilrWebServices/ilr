<?php

namespace Drupal\ilr\Plugin\paragraphs\Behavior;

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
 * Provides a Report Listing plugin.
 *
 * @ParagraphsBehavior(
 *   id = "report_summary_listing",
 *   label = @Translation("Report listing"),
 *   description = @Translation("Configure report listing settings."),
 *   weight = 1
 * )
 */
class ReportSummaryListing extends ParagraphsBehaviorBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Creates a new ReportSummaryListing behavior.
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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_field_manager);
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
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $collection_options = [];
    $default_collection_id = $paragraph->getBehaviorSetting($this->getPluginId(), 'collection');

    foreach ($this->entityTypeManager->getStorage('collection')->loadByProperties(['status' => 1]) as $collection) {
      $collection_options[$collection->id()] = $collection->label();
    }

    asort($collection_options);

    $form['collection'] = [
      '#type' => 'select',
      '#title' => $this->t('Collection'),
      '#options' => $collection_options,
      '#default_value' => $default_collection_id,
      '#required' => TRUE,
    ];

    $form['count'] = [
      '#type' => 'number',
      '#required' => TRUE,
      '#title' => $this->t('Number of reports'),
      '#description' => $this->t('The number of most recent reports to display.'),
      '#min' => 1,
      '#max' => 50,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'count') ?? 6,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraph, EntityViewDisplayInterface $display, $view_mode) {
    $collection_id = $paragraph->getBehaviorSetting($this->getPluginId(), 'collection');

    if (!$collection_id) {
      return;
    }

    $collection = $this->entityTypeManager->getStorage('collection')->load($collection_id);

    if ($collection === NULL) {
      return;
    }

    $collection_item_storage = $this->entityTypeManager->getStorage('collection_item');
    $view_builder = $this->entityTypeManager->getViewBuilder('node');
    $cache_tags = $collection->getCacheTags();
    $cache_tags[] = 'node_list';
    $reports = [];
    $dedupe_group = 'dedupe:collection_item_field_data.id:collection_' . $collection->id();

    $query = $collection_item_storage->getQuery();
    $query->accessCheck(TRUE);
    $query->condition('collection', $collection->id());
    
    $result = $query->execute();
    
    // Filter manually for report_summary content type and published status
    $filtered_result = [];
    foreach ($collection_item_storage->loadMultiple($result) as $collection_item) {
      if ($collection_item->item->entity && 
          $collection_item->item->entity->bundle() === 'report_summary' &&
          $collection_item->item->entity->isPublished()) {
        $filtered_result[] = $collection_item->id();
      }
    }
    
    $result = $filtered_result;
    
    if ($limit = $paragraph->getBehaviorSetting($this->getPluginId(), 'count')) {
      $result = array_slice($result, 0, $limit);
    }

    foreach ($collection_item_storage->loadMultiple($result) as $collection_item) {
      $rendered_entity = $view_builder->view($collection_item->item->entity, 'compact_media');
      $rendered_entity['#collection_item'] = $collection_item;
      $rendered_entity['#cache']['contexts'][] = 'url';
      $reports[] = $rendered_entity;
    }

    $build['listing'] = [
      'items' => $reports,
      '#cache' => [
        'tags' => $cache_tags,
      ],
    ];
  }

  /**
   * Get a node view mode for the list style.
   *
   * @param \Drupal\paragraphs\Entity\Paragraph $paragraph
   *   The paragraph entity.
   *
   * @return string
   *   A node view mode.
   */
  protected function getViewModeForListStyle(Paragraph $paragraph) {
    $list_style = $paragraph->getBehaviorSetting('list_styles', 'list_style') ?? 'carousel';

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
    $collection_id = $paragraph->getBehaviorSetting($this->getPluginId(), 'collection');

    if ($collection_id) {
      $collection = $this->entityTypeManager->getStorage('collection')->load($collection_id);
      $summary[] = [
        'label' => 'Collection',
        'value' => $collection->label(),
      ];
    }

    $summary[] = [
      'label' => 'Show',
      'value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'count') ?? 'All',
    ];

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    return $paragraphs_type->id() === 'report_summary_listing';
  }

}
