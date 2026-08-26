<?php

namespace Drupal\ilr_program_finder\Plugin\search_api\processor;

use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Search API field processor that indexes ???.
 *
 * @todo Decide whether to display discount prices.
 */
#[SearchApiProcessor(
  id: 'ilr_program_instances',
  label: new TranslatableMarkup('ILR program instances'),
  description: new TranslatableMarkup('Course and remote program upcoming date data for indexing.'),
  stages: [
    'add_properties' => 20,
  ],
  locked: TRUE,
  hidden: TRUE,
)]
class ProgramInstances extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource && $datasource->getEntityTypeId() === 'node') {
      $relevant_bundles = array_keys($datasource->getBundles());

      if (in_array('course', $relevant_bundles) || in_array('remote_program', $relevant_bundles)) {
        $definition = [
          'label' => $this->t('Upcoming date instance data'),
          'description' => $this->t('Course and remote program upcoming date data. Tab delimited!'),
          'type' => 'string',
          'processor_id' => $this->getPluginId(),
        ];
        $properties['program_instances'] = new ProcessorProperty($definition);
      }
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $node = $item->getOriginalObject()->getValue();
    $instances = [];
    $format = "%s\t%s\t%s";

    if ($node->bundle() === 'course' && $node->classes->count()) {
      // `classes` is a computed field, and it is sorted by upcoming class
      // dates.
      foreach ($node->classes->referencedEntities() as $class_node) {
        $instances[] = vsprintf($format, [
          $class_node->field_date_start->date->format('M d, Y'),
          $class_node->field_price->value,
          $class_node->field_delivery_method->value,
        ]);
      }
    }
    elseif ($node->bundle() === 'remote_program') {
      $instances[] = vsprintf($format, [
        $node->field_date_start->date->format('M d, Y'),
        $node->field_price->value,
        $node->field_delivery_method->value,
      ]);
    }
    elseif ($node->bundle() === 'event_landing_page') {
      $instances[] = vsprintf($format, [
        $node->event_date->start_date->format('M d, Y'),
        0,
        $node->field_delivery_method->value ?? 'In Person',
      ]);
    }
    else {
      return;
    }

    if (empty($instances)) {
      return;
    }

    $fields = $this->getFieldsHelper()->filterForPropertyPath($item->getFields(), 'entity:node', 'program_instances');

    foreach ($fields as $field) {
      $field->setValues($instances);
    }
  }

}
