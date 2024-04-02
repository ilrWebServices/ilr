<?php

namespace Drupal\ilr\Plugin\paragraphs\Behavior;

use Drupal\Component\Utility\NestedArray;
use Drupal\paragraphs\ParagraphsBehaviorBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a People listing behavior plugin.
 *
 * @ParagraphsBehavior(
 *   id = "ilr_people_listing",
 *   label = @Translation("People listing settings"),
 *   description = @Translation("Configure settings for people listings."),
 *   weight = 3
 * )
 */
class PeopleListing extends ParagraphsBehaviorBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Creates a new Dynamic people listing behavior.
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
    // There is no #parents key in $form, but this may be OK hardcoded.
    $parents = $form['#parents'];
    $parents_input_name = array_shift($parents);
    $parents_input_name .= '[' . implode('][', $parents) . ']';

    // @todo Make this dynamic. @see ProjectListing.
    $persona_types = ['staff' => 'Staff', 'faculty' => 'Faculty'];
    $collection_options = [];
    $default_collection_id = $paragraph->getBehaviorSetting($this->getPluginId(), 'collection');

    $collection_options['57'] = 'YTI';

    // Sort the collection options alphabetically.
    asort($collection_options);

    $form['collection'] = [
      '#type' => 'select',
      '#title' => $this->t('Collection'),
      '#options' => $collection_options,
      '#default_value' => $default_collection_id,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'tagsUpdate'],
        'event' => 'change',
        'wrapper' => 'edit-tags',
      ],
    ];

    $form['persona_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Persona type(s)'),
      '#options' => $persona_types,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'persona_types') ?? array_keys($persona_types),
    ];

    $default_tags = [];

    if ($default_collection_id) {
      $default_collection = $this->entityTypeManager->getStorage('collection')->load($default_collection_id);
      $default_tags = $this->getTagsTermOptions($default_collection);
    }

    $form['tags'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Tag(s)'),
      '#description' => $this->t('Choose a tag (or tags) to filter the listing.'),
      '#options' => $default_tags,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'tags'),
      '#prefix' => '<div id="edit-tags">',
      '#suffix' => '</div>',
      '#validated' => 'true',
    ];

    $form['ignore_sticky'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ignore sticky sorting'),
      '#description' => $this->t('If any people in the listing are marked "Sticky at the top of lists", ignore that and sort alphabetically as usual.'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'ignore_sticky'),
    ];

    return $form;
  }

  /**
   * Callback for Collection select change event.
   */
  public function tagsUpdate(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $collection_id = $form_state->getValue($trigger['#parents']);
    $collection = $this->entityTypeManager->getStorage('collection')->load($collection_id);
    $behavior_form = NestedArray::getValue($form, array_slice($trigger['#array_parents'], 0, -1));
    $behavior_form['tags']['#options'] = $this->getTagsTermOptions($collection);
    return $behavior_form['tags'];
  }

    /**
   * {@inheritdoc}
   */
  public function validateBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    // Ensure that there is at least one persona type selected.
    if (empty(array_filter($form_state->getValue('persona_types')))) {
      $form_state->setError($form['persona_types'], $this->t('Please choose at least one persona type to display.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraphs_entity, EntityViewDisplayInterface $display, $view_mode) {
    $collection_id = $paragraphs_entity->getBehaviorSetting($this->getPluginId(), 'collection');

    if (!$collection_id) {
      return;
    }

    $collection = $this->entityTypeManager->getStorage('collection')->load($collection_id);
    $tags = $paragraphs_entity->getBehaviorSetting($this->getPluginId(), 'tags') ?? [];
    $persona_types = $paragraphs_entity->getBehaviorSetting($this->getPluginId(), 'persona_types') ?? [];
    $list_style = $paragraphs_entity->getBehaviorSetting('list_styles', 'list_style');
    $view_mode = $paragraphs_entity->type->entity->getBehaviorPlugin('list_styles')->getViewModeForListStyle($list_style, $paragraphs_entity);
    $persona_view_builder = $this->entityTypeManager->getViewBuilder('persona');
    $collection_item_storage = $this->entityTypeManager->getStorage('collection_item');
    $cache_tags = $collection->getCacheTags();
    $people = [];
    $dedupe_group = 'dedupe:collection_item_field_data.id:collection_' . $collection->id();

    foreach ($persona_types as $persona_type) {
      $cache_tags[] = 'persona_list:' . $persona_type;
    }

    $query = $collection_item_storage->getQuery();
    $query->accessCheck(TRUE);
    $query->condition('collection', $collection->id());
    $query->condition('type', 'persona_item');
    $query->condition('item.entity:persona.status', 1);
    $query->condition('item.entity:persona.type', $persona_types, 'IN');

    if (!$paragraphs_entity->getBehaviorSetting($this->getPluginId(), 'ignore_sticky')) {
      $query->sort('sticky', 'DESC');
    }

    $query->sort('item.entity:persona.field_last_name', 'ASC');

    if (!empty($tags)) {
      $tags_group = $query->orConditionGroup();

      foreach ($tags as $tag_tid => $tag) {
        $tags_group->condition('field_tags', $tag_tid);
      }
      $query->condition($tags_group);
    }

    // Add a dedupe tag to remove duplicates in similar post_listings. See
    // ilr_query_alter().
    $query->addTag($dedupe_group);
    $result = $query->execute();

    foreach ($collection_item_storage->loadMultiple($result) as $collection_item) {
      $rendered_entity = $persona_view_builder->view($collection_item->item->entity, $view_mode);
      $rendered_entity['#collection_item'] = $collection_item;
      $rendered_entity['#cache']['contexts'][] = 'url';
      $people[] = $rendered_entity;
    }

    $build['listing'] = [
      'items' => $people,
      '#cache' => [
        'tags' => $cache_tags,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(Paragraph $paragraph) {}

  /**
   * Get focus area term options for a given collection.
   *
   * @param \Drupal\collection\Entity\CollectionInterface $collection
   *   A collection entity.
   *
   * @return array
   *   List of term labels keyed by tid.
   */
  protected function getTagsTermOptions($collection) {
    $options = [
      '' => '-- all --',
    ];

    $collection_items = $collection->findItemsByAttribute('collection_persona_tags', "collection_{$collection->id()}_persona_tags");
    /** @var \Drupal\taxonomy\TermStorage $term_manager */
    $term_manager = $this->entityTypeManager->getStorage('taxonomy_term');

    foreach ($collection_items as $collection_item) {
      $vocab = $collection_item->item->entity;
      $category_terms = $term_manager->loadTree($vocab->id(), 0, NULL, TRUE);

      foreach ($category_terms as $term) {
        if ($term->isPublished()) {
          $options[$term->id()] = $term->label();
        }
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   *
   * This behavior is only applicable to people listing paragraphs.
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    return in_array($paragraphs_type->id(), ['people_listing_dynamic']);
  }

}
