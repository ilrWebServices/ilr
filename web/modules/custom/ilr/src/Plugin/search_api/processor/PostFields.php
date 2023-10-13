<?php

namespace Drupal\ilr\Plugin\search_api\processor;

use Drupal\collection\Entity\CollectionItemInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\search_api\Processor\EntityProcessorProperty;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Drupal\search_api\Utility\Utility;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Add the post node fields for this collection item for indexing.
 *
 * @SearchApiProcessor(
 *   id = "ilr_post_fields",
 *   label = @Translation("ILR Post fields"),
 *   description = @Translation("Add the post node fields for this collection item."),
 *   stages = {
 *     "add_properties" = 20,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 *
 * @see https://www.drupal.org/docs/8/modules/search-api/developer-documentation/adding-an-entity-reference-via-a-processor
 */
class PostFields extends ProcessorPluginBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|null
   */
  protected $entityTypeManager;

  /**
   * The fields helper.
   *
   * @var \Drupal\search_api\Utility\FieldsHelperInterface|null
   */
  protected $fieldsHelper;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $processor */
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $processor->setEntityTypeManager($container->get('entity_type.manager'));
    $processor->setFieldsHelper($container->get('search_api.fields_helper'));

    return $processor;
  }

  /**
   * Retrieves the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  public function getEntityTypeManager() {
    return $this->entityTypeManager ?: \Drupal::entityTypeManager();
  }

  /**
   * Sets the entity type manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The new entity type manager.
   *
   * @return $this
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    return $this;
  }

  /**
   * Retrieves the fields helper.
   *
   * @return \Drupal\search_api\Utility\FieldsHelperInterface
   *   The fields helper.
   */
  public function getFieldsHelper() {
    return $this->fieldsHelper ?: \Drupal::service('search_api.fields_helper');
  }

  /**
   * Sets the fields helper.
   *
   * @param \Drupal\search_api\Utility\FieldsHelperInterface $fields_helper
   *   The new fields helper.
   *
   * @return $this
   */
  public function setFieldsHelper(FieldsHelperInterface $fields_helper) {
    $this->fieldsHelper = $fields_helper;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    foreach ($index->getDatasources() as $datasource) {
      if ($datasource->getEntityTypeId() === 'collection_item') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource && $datasource->getEntityTypeId() === 'collection_item') {
      $definition = [
        'label' => $this->t('Post fields'),
        'description' => $this->t("The post node fields for this collected item"),
        'type' => 'entity:node',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['ilr_post_node_fields'] = new EntityProcessorProperty($definition);
      $properties['ilr_post_node_fields']->setEntityTypeId('node');
      $properties['ilr_post_node_fields']->setBundles([
        'post_document',
        'video_post'
      ]);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $collection_item = $item->getOriginalObject()->getValue();

    if (!($collection_item instanceof CollectionItemInterface)) {
      return;
    }

    /** @var \Drupal\search_api\Item\FieldInterface[][] $to_extract */
    $to_extract = [];

    foreach ($item->getFields() as $field) {
      $datasource = $field->getDatasource();
      $property_path = $field->getPropertyPath();

      list($direct, $nested) = Utility::splitPropertyPath($property_path, FALSE);

      if ($datasource
          && $datasource->getEntityTypeId() === 'collection_item'
          && $direct === 'ilr_post_node_fields') {
        $to_extract[$nested][] = $field;
      }
    }

    if (!$to_extract) {
      return;
    }

    $post_node = $this->getEntityTypeManager()
      ->getStorage('node')
      ->load($collection_item->item->entity->id());
    if (!$post_node) {
      return;
    }

    // This is a pretty hack-y work-around to make property extraction work for
    // Views fields, too. In general, adding entities as field values is a
    // pretty bad idea, so this might blow up in some use cases. If not
    // required, the foreach block should thus be commented out.
    if (isset($to_extract[''])) {
      foreach ($to_extract[''] as $field) {
        $field->setValues([$post_node]);
      }
      unset($to_extract['']);
    }

    $this->getFieldsHelper()->extractFields($post_node->getTypedData(), $to_extract, $item->getLanguage());
  }

}

