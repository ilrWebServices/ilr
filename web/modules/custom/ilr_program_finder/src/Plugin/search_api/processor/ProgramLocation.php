<?php

namespace Drupal\ilr_program_finder\Plugin\search_api\processor;

use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Search API field processor that indexes locations for items in the
 * program finder data.
 */
#[SearchApiProcessor(
  id: 'ilr_program_locations',
  label: new TranslatableMarkup('ILR program locations'),
  description: new TranslatableMarkup('Course and event landing page locations for indexing.'),
  stages: [
    'add_properties' => 20,
  ],
  locked: TRUE,
  hidden: TRUE,
)]
class ProgramLocation extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource && $datasource->getEntityTypeId() === 'node') {
      $relevant_bundles = array_keys($datasource->getBundles());

      if (in_array('course', $relevant_bundles) || in_array('event_landing_pages', $relevant_bundles)) {
        // && in_array('resource_library_item', array_keys($datasource->getBundles()))
        $definition = [
          'label' => $this->t('Locations'),
          'description' => $this->t('Finds locations for program finder data.'),
          'type' => 'string',
          'processor_id' => $this->getPluginId(),
          'is_list' => FALSE,
        ];
        $properties['locations'] = new ProcessorProperty($definition);
      }
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $node = $item->getOriginalObject()->getValue();
    $locations = [];

    if ($node->bundle() === 'course' && $node->classes->count()) {
      // `classes` is a computed field, and it is sorted by upcoming class
      // dates.
      foreach ($node->classes->referencedEntities() as $class_node) {
        if (!$class_node->field_address->isEmpty() && $class_node->field_address->locality && $class_node->field_address->administrative_area) {
          $locations[] = $class_node->field_address->locality . ', ' . $class_node->field_address->administrative_area;
        }
      }
    }
    elseif ($node->bundle() === 'event_landing_page') {
      if (!$node->location_address->isEmpty() && $node->location_address->locality && $node->location_address->administrative_area) {
        $locations[] = $node->location_address->locality . ', ' . $node->location_address->administrative_area;
      }
    }
    else {
      return;
    }

    if (empty($locations)) {
      return;
    }

    $fields = $this->getFieldsHelper()->filterForPropertyPath($item->getFields(), 'entity:node', 'locations');

    foreach ($fields as $field) {
      $field->setValues($locations);
    }
  }

}
