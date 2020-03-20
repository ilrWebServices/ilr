<?php

namespace Drupal\collection\Plugin\paragraphs\Behavior;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;
use Drupal\Core\Entity\ContentEntityType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Paragraphs Collection Listing plugin.
 *
 * @ParagraphsBehavior(
 *   id = "collection_listing",
 *   label = @Translation("Collection listing"),
 *   description = @Translation("Configure collection listing settings."),
 *   weight = 1
 * )
 */
class ParagraphsCollectionListing extends ParagraphsBehaviorBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected $entityTypeBundleInfo;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepository
   */
  protected $entityDisplayRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_field.manager'));
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityTypeBundleInfo = $container->get('entity_type.bundle.info');
    $instance->entityDisplayRepository = $container->get('entity_display.repository');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $form['count'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of items'),
      '#description' => $this->t('Leave blank for all items.'),
      '#min' => 1,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'count'),
    ];

    $form['entity_settings'] = [
      '#type' => 'container',
    ];

    foreach ($this->getContentEntityTypeNames() as $machine_name => $label) {
      $view_mode_options = $this->getViewModesForEntity($machine_name);

      if (empty($view_mode_options)) {
        continue;
      }

      $view_mode_options = ['0' => 'Default'] + $view_mode_options;
      $bundle_opts = $paragraph->getBehaviorSetting($this->getPluginId(), ['entity_settings', $machine_name, 'bundles']) ?? [];

      $form['entity_settings'][$machine_name] = [
        '#type' => 'details',
        '#title' => t($label . ' settings'),
        '#open' => (bool) $bundle_opts,
      ];

      $form['entity_settings'][$machine_name]['bundles'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Types to include:'),
        '#options' => $this->getBundlesForEntity($machine_name),
        '#default_value' => $bundle_opts,
      ];

      $form['entity_settings'][$machine_name]['view_mode'] = [
        '#type' => 'select',
        '#title' => $this->t('Display as:'),
        '#options' => $view_mode_options,
        '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), ['entity_settings', $machine_name, 'view_mode']),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    $collection_field_names = self::getCollectionReferenceFieldNames($variables['paragraph']->type->entity);
    $collection_item_storage = $this->entityTypeManager->getStorage('collection_item');
    $view_builders = [];

    foreach ($collection_field_names as $collection_field_name) {
      $paragraph = $variables['paragraph'];
      $collection = $paragraph->$collection_field_name->entity;
      $cache_tags = $collection->getCacheTags();
      $items = [];

      $query = $collection_item_storage->getQuery();
      $query->condition('collection', $paragraph->$collection_field_name->entity->id());

      if ($entity_type_settings = $paragraph->getBehaviorSetting($this->getPluginId(), 'entity_settings')) {
        $group = $query->orConditionGroup();

        foreach ($paragraph->getBehaviorSetting($this->getPluginId(), 'entity_settings') as $entity_type_name => $settings) {
          /** @var \Drupal\Core\Entity\ContentEntityType $entity_type */
          $entity_type = $this->entityTypeManager->getDefinition($entity_type_name);
          $entity_group = $query->andConditionGroup();

          if ($entity_type->getKey('published')) {
            $entity_group->condition('item.entity:' . $entity_type_name . '.' . $entity_type->getKey('published'), 1);
          }

          if ($entity_type->getKey('bundle')) {
            $entity_group->condition('item.entity:' . $entity_type_name . '.' . $entity_type->getKey('bundle'), $settings['bundles'], 'IN');
          }
          else {
            $entity_group->condition('item.target_type', $entity_type_name);
          }

          $group->condition($entity_group);

          // Determine the cache tags for the types of items in this listing.
          // Drupal 8.9 and up allow for more specific tags (per bundle). See
          // https://www.drupal.org/node/3107058.
          if (version_compare(\Drupal::VERSION, '8.9', '>=') && $entity_type->getKey('bundle')) {
            foreach ($settings['bundles'] as $bundle) {
              $cache_tags = array_merge($cache_tags, [$entity_type_name . '_list:' . $bundle]);
            }
          }
          else {
            $cache_tags = array_merge($cache_tags, [$entity_type_name . '_list']);
          }
        }

        $query->condition($group);
      }

      if ($paragraph->getBehaviorSetting($this->getPluginId(), 'count') > 0) {
        $query->range(0, $paragraph->getBehaviorSetting($this->getPluginId(), 'count'));
      }

      $result = $query->execute();

      foreach ($collection_item_storage->loadMultiple($result) as $collection_item) {
        $entity_type = $collection_item->item->entity->getEntityTypeId();

        if (empty($view_builders[$entity_type])) {
          $view_builders[$entity_type] = $this->entityTypeManager->getViewBuilder($entity_type);
        }

        $items[] = $view_builders[$entity_type]->view($collection_item->item->entity, $paragraph->getBehaviorSetting($this->getPluginId(), ['entity_settings', $entity_type, 'view_mode']));
      }

      $variables['content'][$collection_field_name] = [
        '#theme' => 'item_list__collection_listing',
        '#items' => $items,
        '#attributes' => ['class' => 'collection-listing'],
        '#collection_listing' => TRUE,
        '#empty' => $this->t('No items.'),
        '#context' => ['paragraph' => $variables['paragraph']],
        '#cache' => [
          'tags' => $cache_tags
        ],
      ];
    }
  }

  /**
   * @return array
   */
  public static function getCollectionReferenceFieldNames(ParagraphsType $paragraphs_type) {
    $collection_ref_fields = [];
    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('paragraph', $paragraphs_type->id());

    foreach ($field_definitions as $definition) {
      if ($definition->getType() === 'entity_reference' && $definition instanceof FieldConfig) {
        if ($definition->getFieldStorageDefinition()->getSetting('target_type') === 'collection') {
          $collection_ref_fields[] = $definition->getName();
        }
      }
    }

    return $collection_ref_fields;
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
      'label' => 'Items',
      'value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'count') ?? 'All',
    ];

    if ($entity_type_settings = $paragraph->getBehaviorSetting($this->getPluginId(), 'entity_settings')) {
      foreach ($entity_type_settings as $entity_type => $settings) {
        if (empty($settings['bundles'])) {
          continue;
        }

        $summary[] = [
          'label' => $entity_type,
          'value' => implode(', ', $settings['bundles']),
        ];
      }
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * This behavior is only applicable to paragraphs that have a single
   * `collection` entity reference field.
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    return count(self::getCollectionReferenceFieldNames($paragraphs_type)) === 1;
  }

  /**
   * Get all content entity type names.
   *
   * @return array
   */
  protected function getContentEntityTypeNames() {
    $content_entity_types = [];
    $entity_type_definations = $this->entityTypeManager->getDefinitions();

    foreach ($entity_type_definations as $definition) {
      if ($definition instanceof ContentEntityType) {
        // @todo: Check whether the collection allows this content entity type.
        $content_entity_types[$definition->id()] = $definition->getBundleLabel();
      }
    }

    return $content_entity_types;
  }

  /**
   * Get bundles for a given entity type.
   *
   * @param string $entity_type_id
   *
   * @return array
   */
  protected function getBundlesForEntity($entity_type_id) {
    $bundles = [];

    foreach ($this->entityTypeBundleInfo->getBundleInfo($entity_type_id) as $bundle_key => $bundle_info) {
      $bundles[$bundle_key] = (string) $bundle_info['label'];
    }

    return $bundles;
  }

  /**
   * Get view_modes for a given entity type.
   *
   * @param string $entity_type_id
   *
   * @return array
   */
  protected function getViewModesForEntity($entity_type_id) {
    $view_modes = [];

    foreach ($this->entityDisplayRepository->getViewModes($entity_type_id) as $view_mode => $display_info) {
      if ($display_info['status'] === TRUE && $view_mode !== 'token') {
        $view_modes[$view_mode] = $display_info['label'];
      }
    }
    return $view_modes;
  }

}
