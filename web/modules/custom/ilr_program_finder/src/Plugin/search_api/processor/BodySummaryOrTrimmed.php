<?php

namespace Drupal\ilr_program_finder\Plugin\search_api\processor;

use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Search API field processor that indexes the body field using the summary or trimmed formatter.
 */
#[SearchApiProcessor(
  id: 'ilr_body_summary_or_trimmed',
  label: new TranslatableMarkup('ILR body summary or trimmed'),
  description: new TranslatableMarkup('Indexes a body field using the summary or trimmed field formatter.'),
  stages: [
    'add_properties' => 20,
  ],
  locked: TRUE,
  hidden: TRUE,
)]
class BodySummaryOrTrimmed extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource && $datasource->getEntityTypeId() === 'node') {
      $definition = [
        'label' => $this->t('Body (Summary or trimmed)'),
        'description' => $this->t('The body field, rendered using the "Summary or trimmed" formatter.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['body_summary_or_trimmed'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $node = $item->getOriginalObject()->getValue();

    if (!$node->hasField('body')) {
      return;
    }

    $body = $node->get('body');
    $text = $body->summary ? $body->summary : $body->value;

    if (empty($text)) {
      return;
    }

    $fields = $this->getFieldsHelper()->filterForPropertyPath($item->getFields(), 'entity:node', 'body_summary_or_trimmed');

    foreach ($fields as $field) {
      $field->setValues([text_summary($text)]);
    }
  }

}
