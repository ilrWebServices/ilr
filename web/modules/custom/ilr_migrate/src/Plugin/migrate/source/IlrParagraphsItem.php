<?php

namespace Drupal\ilr_migrate\Plugin\migrate\source;

use Drupal\paragraphs\Plugin\migrate\source\d7\ParagraphsItem;

/**
 * D7 Paragraphs Item source plugin.
 *
 * Available configuration keys:
 * - bundle: (optional) If supplied, this will only return paragraphs
 *   of that particular type.
 * - field_name: (optional) If supplied, restrict to and join on the supplied
 *   field.
 * - field_entity_type: (optional) If field_name is specified, restrict to this
 *   entity type (e.g. node).
 * - field_entity_bundle: (optional) If field_name is specified, restrict to
 *   this bundle name (e.g. page).
 *
 * @MigrateSource(
 *   id = "ilr_d7_paragraphs_item",
 *   source_module = "paragraphs"
 * )
 */
class IlrParagraphsItem extends ParagraphsItem {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();

    if ($this->configuration['field_name']) {
      $field_name = $this->configuration['field_name'];
      $query->join("field_data_{$field_name}", 'f', "f.{$field_name}_value = p.item_id and f.{$field_name}_revision_id = p.revision_id");

      if ($this->configuration['field_entity_type']) {
        $query->condition('f.entity_type', $this->configuration['field_entity_type']);
      }

      if ($this->configuration['field_entity_bundle']) {
        $query->condition('f.bundle', $this->configuration['field_entity_bundle']);
      }
    }

    return $query;
  }

}
