<?php

namespace Drupal\ilr_program_finder\Plugin\paragraphs\Behavior;

use DateTime;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paragraphs\Attribute\ParagraphsBehavior;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;
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
    $form['reverse_component'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reverse Component'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'reverse_component') ?? FALSE,
      '#description' => $this->t('When checked, the image will be aligned to the left in the side-by-side layout.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    if ($variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'reverse_component')) {
      $variables['attributes']['class'][] = 'cu-layoutscheme--reversed';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraphs_entity, EntityViewDisplayInterface $display, $view_mode) {
    /** @var \Drupal\search_api\Entity\Index $index  */
    $index = $this->entityTypeManager->getStorage('search_api_index')->load('program_finder_data');

    $query = $index->query();
    $query->addCondition('upcoming_dates', time(), '>');
    $query->sort('upcoming_dates');
    $results = $query->execute();
    $items = [];

    /** @var \Drupal\search_api\Item\Item $item  */
    foreach ($results->getResultItems() as $item) {
      $dates = $item->getField('upcoming_dates')->getValues() ?? [];

      $facet_dates = array_map(function($date) {
        $datetime = DrupalDateTime::createFromTimestamp($date);
        return $datetime->format('F Y');
      }, $dates);


      $items[] = [
        '#type' => 'component',
        '#component' => 'ilr_program_finder:program_finder_item',
        '#props' => [
          'item_id' => $item->getId(),
          'topics' => $item->getField('topic')->getValues() ?? [],
          'delivery_method' => $item->getField('delivery_method')->getValues()[0] ?? '',
          'upcoming_dates' => $facet_dates,
        ],
        '#slots' => [
          'title' => $item->getField('title')->getValues()[0] ?? '',
          'summary' => $item->getField('summary')->getValues()[0] ?? '',
        ],
      ];

      // $data[] = [
      //   'id' => $item->getId(),
      //   // 'score' => $item->getScore(),
      //   'group' => $item->getField('group')->getValues()[0],
      //   'title' => $item->getField('title')->getValues()[0] ?? '',
      //   'status' => $item->getField('status')->getValues()[0],
      //   'delivery_method' => $item->getField('delivery_method')->getValues()[0] ?? '',
      //   'summary' => $item->getField('summary')->getValues()[0] ?? '',
      //   'topics' => $item->getField('topic')->getValues() ?? [],
      //   'type' => $item->getField('type')->getValues()[0] ?? '',
      //   'upcoming_dates' => $item->getField('upcoming_dates')->getValues(),
      // ];
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
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(Paragraph $paragraph) {
    $summary = [];

    if ($paragraph->getBehaviorSetting($this->getPluginId(), 'reverse_component')) {
      $summary[] = [
        'label' => 'Reversed Component',
        'value' => 'True',
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
