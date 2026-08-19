<?php

namespace Drupal\ilr_program_finder\Plugin\search_api\processor;

use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Search API field processor that indexes delivery methods (including all
 * classes) for items in the program finder data.
 *
 * Even though both course and remote_program nodes have a delivery_method
 * field, courses have multiple upcoming classes, which also have that field,
 * and it can sometimes be different. So we need to get all unique values for
 * classes.
 */
#[SearchApiProcessor(
  id: 'ilr_delivery_methods',
  label: new TranslatableMarkup('ILR delivery methods'),
  description: new TranslatableMarkup('Course and remote program delivery methods for indexing.'),
  stages: [
    'add_properties' => 20,
  ],
  locked: TRUE,
  hidden: TRUE,
)]
class ProgramDeliveryMethods extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource && $datasource->getEntityTypeId() === 'node') {
      $relevant_bundles = array_keys($datasource->getBundles());

      if (in_array('course', $relevant_bundles) || in_array('remote_program', $relevant_bundles)) {
        $definition = [
          'label' => $this->t('Delivery methods (fancy)'),
          'description' => $this->t('Calculates delivery for program finder data in a fancy way.'),
          'type' => 'string',
          'processor_id' => $this->getPluginId(),
          'is_list' => FALSE,
        ];
        $properties['delivery_methods'] = new ProcessorProperty($definition);
      }
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $node = $item->getOriginalObject()->getValue();
    $delivery_methods = [];

    if ($node->bundle() === 'course' && $node->classes->count()) {
      // `classes` is a computed field, and it is sorted by upcoming class
      // dates.
      foreach ($node->classes->referencedEntities() as $class_node) {
        $delivery_methods[] = $class_node->field_delivery_method->value;
      }
    }
    elseif ($node->bundle() === 'remote_program') {
      $delivery_methods[] = $node->field_delivery_method->value;
    }
    else {
      return;
    }

    if (empty($delivery_methods)) {
      return;
    }

    $fields = $this->getFieldsHelper()->filterForPropertyPath($item->getFields(), 'entity:node', 'delivery_methods');

    foreach ($fields as $field) {
      $field->setValues(array_unique($delivery_methods));
    }
  }

}
