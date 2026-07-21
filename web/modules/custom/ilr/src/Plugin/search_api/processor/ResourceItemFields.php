<?php

namespace Drupal\ilr\Plugin\search_api\processor;

use Drupal\collection\Entity\CollectionItemInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Search API field processor that provides special resource item collected item
 * fields for indexing.
 *
 * Not the fields on the collection_item itself, but rather fields on
 * resource_item collected items, e.g. nodes. Specifially, this plugin
 * aggregates different date fields into a single `resource_date` property.
 */
#[SearchApiProcessor(
  id: 'ilr_resource_item_fields',
  label: new TranslatableMarkup('ILR resource item fields'),
  description: new TranslatableMarkup('Resource item collection item reference fields for indexing.'),
  stages: [
    'add_properties' => 20,
  ],
  locked: TRUE,
  hidden: TRUE,
)]
class ResourceItemFields extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource && $datasource->getEntityTypeId() === 'collection_item' && in_array('resource_library_item', array_keys($datasource->getBundles()))) {
      $definition = [
        'label' => $this->t('Resource date'),
        'description' => $this->t('Combines different date fields.'),
        'type' => 'date',
        'processor_id' => $this->getPluginId(),
        'is_list' => FALSE,
      ];
      $properties['resource_date'] = new ProcessorProperty($definition);
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

    $entity = $collection_item->item->entity;

    // Most post content types have a published date field.
    if ($entity->hasField('field_published_date') && !$entity->get('field_published_date')->isEmpty()) {
      $datetime = new DrupalDateTime($entity->get('field_published_date')->value);
    }
    // Any node that implements the bundle class
    // \Drupal\ilr\Entity\EventNodeBase has an event_date field.
    elseif ($entity->hasField('event_date') && !$entity->get('event_date')->isEmpty()) {
      $datetime = new DrupalDateTime($entity->get('event_date')->value);
    }
    // Most content entities have a `changed` base field.
    elseif ($entity->hasField('changed') && !$entity->get('changed')->isEmpty()) {
      $datetime = DrupalDateTime::createFromTimestamp($entity->get('changed')->value);
    }
    else {
      return;
    }

    $fields = $this->getFieldsHelper()->filterForPropertyPath($item->getFields(), 'entity:collection_item', 'resource_date');

    foreach ($fields as $field) {
      $field->setValues([$datetime->getTimestamp()]);
    }
  }

}
