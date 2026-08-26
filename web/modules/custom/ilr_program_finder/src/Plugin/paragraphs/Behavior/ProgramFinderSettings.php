<?php

namespace Drupal\ilr_program_finder\Plugin\paragraphs\Behavior;

use DateTime;
use Drupal\Component\Utility\Html;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\paragraphs\Attribute\ParagraphsBehavior;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;
use Drupal\search_api\Query\ConditionGroup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides settings for the program finder component.
 */
#[ParagraphsBehavior(
  id: 'ilr_program_finder',
  label: new TranslatableMarkup('Program finder settings'),
  description: new TranslatableMarkup('Settings and functionality for program finder components.'),
  weight: 2,
)]
class ProgramFinderSettings extends ParagraphsBehaviorBase {

/**
   * Creates a new ProgramFinderSettings behavior.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityFieldManagerInterface $entity_field_manager,
    protected EntityTypeManagerInterface $entityTypeManager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_field_manager);
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
    /** @var \Drupal\search_api\Entity\Index $index  */
    $index = $this->entityTypeManager->getStorage('search_api_index')->load('program_finder_data');
    $today = new DrupalDateTime('midnight yesterday');
    $query = $index->query();
    $query->addCondition('upcoming_dates', $today->getTimestamp(), '>=');
    $results = $query->execute();
    $tags = [];

    foreach ($results->getResultItems() as $item) {
      foreach ($item->getField('tags')->getValues() as $value) {
        $tags[$value] = $value;
      }
    }

    ksort($tags);

    $form['tags'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Tags'),
      '#options' => $tags,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'tags') ?? FALSE,
      '#description' => $this->t('Lots of esplainin to do here.'),
    ];

    $form['tag_query_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Tag query type'),
      '#options' => ['Any' => 'Any', 'All' => 'All'],
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'tag_query_type') ?? 'Any',
      '#description' => $this->t('Lots more esplainin to do here.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraphs_entity, EntityViewDisplayInterface $display, $view_mode) {
    /** @var \Drupal\search_api\Entity\Index $index  */
    $index = $this->entityTypeManager->getStorage('search_api_index')->load('program_finder_data');
    $today = new DrupalDateTime('midnight yesterday');

    $query = $index->query();
    $query->addCondition('upcoming_dates', $today->getTimestamp(), '>=');

    if ($tags = $paragraphs_entity->getBehaviorSetting($this->getPluginId(), 'tags')) {
      if ($paragraphs_entity->getBehaviorSetting($this->getPluginId(), 'tag_query_type') === 'All') {
        $tag_group = new ConditionGroup('AND');

        foreach ($tags as $tag) {
          $tag_group->addCondition('tags', $tag);
        }

        $query->addConditionGroup($tag_group);
      }
      else {
        $query->addCondition('tags', $tags, 'IN');
      }
    }

    $query->sort('upcoming_dates');
    $results = $query->execute();
    $items = [];

    if ($results->getResultCount() == 0) {
      return;
    }

    /** @var \Drupal\search_api\Item\Item $item  */
    foreach ($results->getResultItems() as $item) {
      $dates = $item->getField('upcoming_dates')->getValues() ?? [];

      $facet_dates = array_map(function($date) {
        $datetime = DrupalDateTime::createFromTimestamp($date);
        return $datetime->format('F Y');
      }, $dates);

      $summary = $item->getField('summary')->getValues()[0] ?? '';

      $topics = $item->getField('topics')->getValues();
      $keywords = $item->getField('keywords')->getValues();
      $all_topics = $topics + $keywords;

      $items[] = [
        '#type' => 'component',
        '#component' => 'ilr_program_finder:program_finder_item',
        '#props' => [
          'item_id' => $item->getId(),
          'topics' => $all_topics ?: ['Uncategorized'],
          'delivery_methods' => $item->getField('delivery_methods')->getValues() ?? [],
          'program_instances' => $item->getField('program_instances')->getValues() ?? [],
          'upcoming_dates' => $facet_dates,
          'url' => $item->getField('url')->getValues()[0],
        ],
        '#slots' => [
          'title' => $item->getField('title')->getValues()[0] ?? '',
          'summary' => Html::decodeEntities(strip_tags($summary)),
        ],
      ];
    }

    $build['items'] = [
      '#type' => 'component',
      '#component' => 'ilr_program_finder:program_finder',
      '#props' => [
        'status' => true,
      ],
      '#slots' => [
        'items' => $items,
      ],
      '#attached' => ['library' => ['union_organizer/button']],
      '#cache' => [
        'max-age' => 7200,
        'tags' => ['node_list:course', 'node_list:class', 'node_list:remote_program'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(Paragraph $paragraph) {
    $summary = [];

    if ($paragraph->getBehaviorSetting($this->getPluginId(), 'tags')) {
      $summary[] = [
        'label' => 'Tags',
        'value' => implode(', ', array_values($paragraph->getBehaviorSetting($this->getPluginId(), 'tags'))),
      ];
    }

    if ($paragraph->getBehaviorSetting($this->getPluginId(), 'tag_query_type')) {
      $summary[] = [
        'label' => 'Type',
        'value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'tag_query_type'),
      ];
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * This behavior is applicable to the `program_finder` paragraph type.
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    return $paragraphs_type->id() === 'program_finder';
  }

}
