<?php

namespace Drupal\ilr\Plugin\search_api\processor;

use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\collection\Entity\CollectionItemInterface;
use Drupal\search_api\Processor\EntityProcessorProperty;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Utility\Utility;

/**
 * Add the post node fields for this collection item for indexing.
 *
 * @see https://www.drupal.org/docs/8/modules/search-api/developer-documentation/adding-an-entity-reference-via-a-processor
 *
 * @todo This might be better named `collected_item_fields` since it could work with any collected content entity.
 */
#[SearchApiProcessor(
  id: 'ilr_post_fields',
  label: new TranslatableMarkup('ILR Post fields'),
  description: new TranslatableMarkup('Add the post node fields for this collection item.'),
  stages: [
    'add_properties' => 20,
  ],
  locked: TRUE,
  hidden: TRUE,
)]
class PostFields extends ProcessorPluginBase {

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
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
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
        'video_post',
        'post',
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

      [$direct, $nested] = Utility::splitPropertyPath($property_path, FALSE);

      if ($datasource
          && $datasource->getEntityTypeId() === 'collection_item'
          && $direct === 'ilr_post_node_fields') {
        $to_extract[$nested][] = $field;
      }
    }

    if (!$to_extract) {
      return;
    }

    $post_node = $collection_item->item->entity;

    if (!$post_node) {
      return;
    }

    // This is a pretty hack-y work-around to make property extraction work for
    // Views fields, too. In general, adding entities as field values is a
    // pretty bad idea, so this might blow up in some use cases. If not
    // required, the foreach block should thus be commented out.
    if (isset($to_extract[''])) {
      // Foreach ($to_extract[''] as $field) {
      //   $field->setValues([$post_node]);
      // }
      unset($to_extract['']);
    }

    $this->getFieldsHelper()->extractFields($post_node->getTypedData(), $to_extract, $item->getLanguage());
  }

}
