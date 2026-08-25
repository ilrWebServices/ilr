<?php

namespace Drupal\ilr_program_finder\Plugin\search_api\processor;

use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Search API field processor that adds tags for program finder items.
 *
 * These tags can be selected when placing a program finder component to limit
 * the items.
 */
#[SearchApiProcessor(
  id: 'ilr_program_finder_tags',
  label: new TranslatableMarkup('ILR program finder tags'),
  description: new TranslatableMarkup('Indexes auto-generated tags for items in the program finder date index.'),
  stages: [
    'add_properties' => 20,
  ],
  locked: TRUE,
  hidden: TRUE,
)]
class ProgramFinderTags extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource && $datasource->getEntityTypeId() === 'node') {
      $definition = [
        'label' => $this->t('Program finder item tags'),
        'description' => $this->t('Auto-generated tags for program finder items.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['program_finder_tags'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    /** @var \Drupal\collection\CollectionContentManager $collection_content_manager */
    $collection_content_manager = \Drupal::service('collection.content_manager');
    $node = $item->getOriginalObject()->getValue();
    $tags = [];


    // content type
    $tags[] = 'type:' . $node->bundle();

    // content entities collections
    $collections = $collection_content_manager->getCollectionsForEntity($node, FALSE);

    foreach ($collections as $collection) {
      $tags[] = 'collection:' . $collection->label();
    }

    // salesforce fields like program or department.
    if ($node->bundle() === 'course') {
      // @see \Drupal\ilr_salesforce\EventSubscriber\SalesforceEventSubscriber::pullPresaveCourseNode().
      foreach ($node->field_sponsor->referencedEntities() as $sponsor) {
        $tags[] = 'salesforce-department:' . $sponsor->label();
      }
    }

    if (empty($tags)) {
      return;
    }

    $fields = $this->getFieldsHelper()->filterForPropertyPath($item->getFields(), 'entity:node', 'program_finder_tags');

    foreach ($fields as $field) {
      $field->setValues($tags);
    }
  }

}
