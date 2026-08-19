<?php

namespace Drupal\ilr_program_finder\Plugin\search_api\processor;

use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Search API field processor that indexes upcoming dates for items in the
 * program finder data.
 */
#[SearchApiProcessor(
  id: 'ilr_upcoming_program_dates',
  label: new TranslatableMarkup('ILR upcoming program dates'),
  description: new TranslatableMarkup('Course and remote program upcoming dates for indexing.'),
  stages: [
    'add_properties' => 20,
  ],
  locked: TRUE,
  hidden: TRUE,
)]
class UpcomingProgramDates extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource && $datasource->getEntityTypeId() === 'node') {
      $relevant_bundles = array_keys($datasource->getBundles());

      if (in_array('course', $relevant_bundles) || in_array('remote_program', $relevant_bundles)) {
        // && in_array('resource_library_item', array_keys($datasource->getBundles()))
        $definition = [
          'label' => $this->t('Upcoming dates'),
          'description' => $this->t('Calculates upcoming dates for program finder data.'),
          'type' => 'date',
          'processor_id' => $this->getPluginId(),
          'is_list' => FALSE,
        ];
        $properties['upcoming_dates'] = new ProcessorProperty($definition);
      }
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $node = $item->getOriginalObject()->getValue();
    $datetimes = [];

    if ($node->bundle() === 'course' && $node->classes->count()) {
      // `classes` is a computed field, and it is sorted by upcoming class
      // dates.
      foreach ($node->classes->referencedEntities() as $class_node) {
        $datetimes[] = $class_node->field_date_start->date->getTimestamp();
      }
    }
    elseif ($node->bundle() === 'remote_program') {
      $datetimes[] = $node->field_date_start->date->getTimestamp();
    }
    else {
      return;
    }

    if (empty($datetimes)) {
      return;
    }

    $fields = $this->getFieldsHelper()->filterForPropertyPath($item->getFields(), 'entity:node', 'upcoming_dates');

    foreach ($fields as $field) {
      $field->setValues($datetimes);
    }
  }

}
