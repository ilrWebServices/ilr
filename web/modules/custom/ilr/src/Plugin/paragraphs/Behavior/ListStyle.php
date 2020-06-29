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
   */
  protected $listStyles = [
    'grid' => 'Grid',
    'grid-compact' => 'Compact grid',
    'list-compact' => 'Compact list',
    'grid-featured' => 'Featured Grid',
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
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $paragraphs_type = $form_state->getFormObject()->getEntity();

    if ($paragraphs_type->isNew()) {
      return [];
    }

    $config = $this->getConfiguration();

    $form['list_styles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Types to include:'),
      '#options' => $this->listStyles,
      '#default_value' => $config['list_styles'] ?? $this->listStyles,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['list_styles'] = $form_state->getValue('list_styles');
  }

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();
    $style_options = [];

    if (empty($config['list_styles'])){
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
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'list_style'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    $paragraph = $variables['paragraph'];

    if ($list_style = $paragraph->getBehaviorSetting($this->getPluginId(), 'list_style')) {
      $classes = $this->getListStyleClasses($paragraph);
      $variables['attributes']['class'] = $classes;
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
    // rendered entity.
    $build_fields = array_keys(array_filter($build, function($v) {
      return is_array($v) && isset($v['#theme']) && $v['#theme'] === 'field' && isset($v['#formatter']) && $v['#formatter'] === 'entity_reference_entity_view';
    }));

    foreach ($build_fields as $field) {
      $element = &$build[$field];

      foreach (array_keys(iterator_to_array($element['#items'])) as $key) {
        $element[$key]['#view_mode'] = $this->getViewModeForListStyle($list_style, $key + 1);

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
    return in_array($paragraphs_type->id(), ['simple_collection_listing', 'curated_post_listing', 'collection_listing_publication', 'curated_course_listing']);
  }

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
  public function getViewModeForListStyle($list_style, $post_number) {
    switch ($list_style) {
      case 'grid-compact':
        return 'teaser_compact';

      case 'list-compact':
        return 'mini';

      case 'grid-featured':
        return $post_number === 1 ? 'featured' : 'teaser';

      case 'banner':
        return 'banner';

      default:
        return 'teaser';
    }
  }

  /**
   * Get CSS classes for a given list style.
   *
   * @param $paragraph Paragraph
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
      elseif (strpos($list_style, 'grid') === 0) {
        $classes[] = 'cu-grid cu-grid--3col';
      }
    }

    return $classes;
  }

}
