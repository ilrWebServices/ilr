<?php

namespace Drupal\ilr\Plugin\paragraphs\Behavior;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
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
 *   id = "list_styles",
 *   label = @Translation("List style"),
 *   description = @Translation("Configure list style options."),
 *   weight = 3
 * )
 */
class ListStyle extends ParagraphsBehaviorBase {

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
    'grid-compact' => 'Compact grid',
    'list-compact' => 'Compact list',
    'list-summary' => 'Summary list',
    'grid-bricks' => 'Bricks',
    'grid-bricks--reversed' => 'Bricks (reversed)',
    'grid-featured' => 'Featured Grid',
    'grid-featured--wide' => 'Featured Grid (Wide)',
    'banner' => 'Banner',
    'tabular-list' => 'Tabular list',
    'select-list' => 'Select list',
    'main_content' => 'Main content',
    'link-cloud' => 'Link cloud',
    'carousel' => 'Carousel',
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
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $paragraphs_type = $form_state->getFormObject()->getEntity();

    if ($paragraphs_type->isNew()) {
      return [];
    }

    $config = $this->getConfiguration();

    // There is no #parents key in $form, but this may be OK hardcoded.
    $subform_prefix = 'behavior_plugins[list_styles][settings]';

    $form['list_styles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Types to include:'),
      '#options' => $this->listStyles,
      '#default_value' => $config['list_styles'] ?? array_keys($this->listStyles),
    ];

    $form['columns']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow editors to choose grid column count'),
      '#default_value' => $config['columns_enabled'] ?? 0,
    ];

    $form['columns']['min'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum'),
      '#description' => $this->t('The minimum # of columns allowed.'),
      '#min' => 1,
      '#max' => 4,
      '#states' => [
        'visible' => [
          ':input[name="' . $subform_prefix . '[columns][enabled]"]' => ['checked' => TRUE],
        ],
      ],
      '#default_value' => $config['columns_min'] ?? '',
    ];

    $form['columns']['max'] = [
      '#type' => 'number',
      '#title' => $this->t('Maxium'),
      '#description' => $this->t('The maximum # of columns allowed.'),
      '#min' => 2,
      '#max' => 5,
      '#states' => [
        'visible' => [
          ':input[name="' . $subform_prefix . '[columns][enabled]"]' => ['checked' => TRUE],
        ],
      ],
      '#default_value' => $config['columns_max'] ?? '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['list_styles'] = $form_state->getValue('list_styles');
    $column_settings = $form_state->getValue('columns');
    $this->configuration['columns_enabled'] = $column_settings['enabled'];
    $this->configuration['columns_min'] = $column_settings['min'];
    $this->configuration['columns_max'] = $column_settings['max'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();
    $style_options = [];

    $parents = $form['#parents'];
    $parents_input_name = array_shift($parents);
    $parents_input_name .= '[' . implode('][', $parents) . ']';

    if (empty($config['list_styles'])) {
      return;
    }

    foreach ($config['list_styles'] as $list_style) {
      if (empty($list_style)) {
        continue;
      }

      $style_options[$list_style] = $this->listStyles[$list_style];
    }

    $form['list_style'] = [
      '#type' => 'select',
      '#title' => $this->t('List style'),
      '#description' => '',
      '#options' => $style_options,
      '#required' => TRUE,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'list_style') ?? reset($style_options),
    ];

    if ($config['columns_enabled']) {
      $form['columns'] = [
        '#type' => 'number',
        '#title' => $this->t('Columns'),
        '#description' => $this->t('The # of columns to show at wide screens.'),
        '#min' => $config['columns_min'],
        '#max' => $config['columns_max'],
        '#states' => [
          'visible' => [
            ':input[name="' . $parents_input_name . '[list_style]"]' => [
              ['value' => 'grid'],
              ['value' => 'grid-compact'],
            ],
          ],
        ],
        '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'columns') ?? 3,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    $paragraph = $variables['paragraph'];

    // All other behaviors that generate a list from a query should place their
    // items in $build['listing']['items'] in the view() method.
    if (isset($variables['content']['listing']['items'])) {
      $item_count = count($variables['content']['listing']['items']);
    }
    // All paragraphs that generate a curated list via entity reference should
    // be picked up here. We only check the first entity reference field.
    else {
      $item_count = 0;

      foreach ($variables['content'] as $content_item) {
        if (isset($content_item['#items']) && $content_item['#items'] instanceof EntityReferenceFieldItemList) {
          /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $items */
          $items = $content_item['#items'];
          $item_count = $items->count();
          break;
        }
      }
    }

    $variables['attributes']['data-itemcount'] = $item_count;

    if ($list_style = $paragraph->getBehaviorSetting($this->getPluginId(), 'list_style')) {
      foreach ($this->getListStyleClasses($paragraph) as $class) {
        $variables['attributes']['class'][] = $class;
      }

      if (strpos($list_style, 'grid') === 0) {
        $variables['#attached']['library'][] = 'union_organizer/grid';
      }

      if ($list_style === 'carousel') {
        if (isset($variables['content']['listing']['items'])) {
          $variables['carousel_items'] = $variables['content']['listing']['items'];
        }
      }

      // @todo Replace with CSS logic based on data-itemcount.
      if ($list_style === 'grid-featured') {
        $variables['attributes']['style'] = '--featured-grid-rows: ' . (($item_count < 4) ? 2 : 3);
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * Update the view mode on entity reference fields on this paragraph
   * depending on the list style.
   */
  public function view(array &$build, Paragraph $paragraphs_entity, EntityViewDisplayInterface $display, $view_mode) {
    if (!$list_style = $paragraphs_entity->getBehaviorSetting($this->getPluginId(), 'list_style')) {
      return;
    }

    // Only update entity reference fields that are configured to display a
    // rendered entity. Query-based listings figure out the view mode
    // themselves, but that could move here, too, someday.
    $build_fields = array_keys(array_filter($build, function ($v) {
      return is_array($v) && isset($v['#theme']) && $v['#theme'] === 'field' && isset($v['#formatter']) && in_array($v['#formatter'], ['entity_reference_entity_view', 'collected_item_entity_formatter']);
    }));

    foreach ($build_fields as $field) {
      $element = &$build[$field];

      foreach (array_keys(iterator_to_array($element['#items'])) as $key) {
        // Ensure that the item is in the render array.
        if (empty($element[$key])) {
          continue;
        }

        $original_view_mode = $element[$key]['#view_mode'];
        $view_mode_for_liststyle = $this->getViewModeForListStyle($list_style, $paragraphs_entity);
        $cache_key_view_mode_key = array_search($original_view_mode, $element[$key]['#cache']['keys']);

        if ($original_view_mode !== $view_mode_for_liststyle) {
          $element[$key]['#view_mode'] = $view_mode_for_liststyle;

          if ($cache_key_view_mode_key) {
            $element[$key]['#cache']['keys'][$cache_key_view_mode_key] = $view_mode_for_liststyle;
          }
        }

        // Merge the paragraph cache tags with the entity cache tags. This
        // will invalidate the entities when the paragraph list style is
        // modified.
        $element[$key]['#cache']['tags'] = array_merge($element[$key]['#cache']['tags'], $build['#cache']['tags']);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(Paragraph $paragraph) {
    $summary = [];

    if ($paragraph->getBehaviorSetting($this->getPluginId(), 'list_style')) {
      $style = $this->listStyles[$paragraph->getBehaviorSetting($this->getPluginId(), 'list_style')];
    }
    else {
      $style = '';
    }

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
    return in_array($paragraphs_type->id(), [
      'simple_collection_listing',
      'curated_post_listing',
      'collection_listing_publication',
      'curated_course_listing',
      'event_listing',
      'people_listing',
      'people_listing_dynamic',
      'project_listing',
      'organization_listing',
      'link_listing',
      'report_summary_listing',
    ]);
  }

  /**
   * Get a node view mode for a given list style.
   *
   * @param string $list_style
   *   One of the list style machine names from this::list_styles.
   *
   * @return string
   *   A node view mode.
   */
  public function getViewModeForListStyle($list_style, ParagraphInterface $paragraph = NULL) {
    // Some entities, such as personas, have a teaser_featured view mode.
    $has_personas = $paragraph && in_array($paragraph->bundle(), ['people_listing', 'people_listing_dynamic']);
    if ($has_personas && $list_style === 'grid-featured') {
      return 'teaser_featured';
    }

    switch ($list_style) {
      case 'grid-compact':
        return 'teaser_compact';

      case 'list-compact':
      case 'select-list':
        return 'mini';

      case 'tabular-list':
        return 'tabular_item';

      case 'banner':
        return 'banner';

      case 'main_content':
        return 'main_content';

      case 'carousel':
        return 'compact_media';

      default:
        return 'teaser';
    }
  }

  /**
   * Get CSS classes for a given list style.
   *
   * @param \Drupal\paragraphs\Entity\Paragraph $paragraph
   *   The paragraph entity.
   *
   * @return array
   *   An array of class names for the element.
   */
  public function getListStyleClasses(Paragraph $paragraph) {
    $classes = [];

    if ($list_style = $paragraph->getBehaviorSetting($this->getPluginId(), 'list_style')) {
      $classes[] = 'cu-list--' . $list_style;

      if ($list_style === 'grid-featured') {
        $classes[] = 'cu-grid cu-grid--featured';
      }
      elseif ($list_style === 'grid-featured--standard') {
        $classes[] = 'cu-grid cu-grid--featured--standard';
      }
      elseif ($list_style === 'grid-featured--wide') {
        $classes[] = 'cu-grid cu-grid--featured--wide';
      }
      elseif (strpos($list_style, 'grid') === 0) {
        $classes[] = 'cu-grid';

        $columns = $paragraph->getBehaviorSetting($this->getPluginId(), 'columns') ?? 3;
        $classes[] = 'cu-grid--' . $columns . 'col';
      }
    }

    return $classes;
  }

}
