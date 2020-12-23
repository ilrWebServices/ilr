<?php

namespace Drupal\ilr_migrate\Plugin\migrate\source;

use Drupal\node\Plugin\migrate\source\d7\Node as D7_node;
use Drupal\migrate\Row;

/**
 * Drupal 7 node source from db with a Drupal 8 nid from a Salesforce join.
 *
 * The idea here is to map the drupal7 node ids with D8 nids by using the
 * Salesforce ID from mapping objects.
 *
 * We do this because the D8 site will already have some nodes (courses and
 * instructors, for example) that have been imported via the Salesforce module.
 * These imported nodes need to be updated with D7 content (body, images, etc.)
 * rather than imported from scratch, so we'll need the Drupal 8 nid as part of
 * the source dataset.
 *
 * See https://virtuoso-performance.com/blog/vpadmin/importing-specific-fields-overwriteproperties
 * for information on using `overwrite_properties` to do this.
 *
 * The assumption is that if a D7 node and a D8 node both have a Salesforce
 * mapping to the same Salesforce ID, then they are effectively the same node.
 *
 * @MigrateSource(
 *   id = "d7_node_sf_mapping",
 *   source_module = "node"
 * )
 */
class D7NodeWithSalesforceMapping extends D7_node {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();

    // Join nodes to the Salesforce mapping object table. Use `leftJoin()` to
    // get _all_ node records. `innerJoin()` will only get nodes that have a
    // salesforce mapping. This code assumes that the D7 source install has the
    // salesforce module enabled.
    $query->innerJoin('salesforce_mapping_object', 'sf', 'sf.entity_type = \'node\' AND n.nid = sf.entity_id');
    $query->addField('sf', 'salesforce_id');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $sf_mapped_object_storage = $this->entityManager->getStorage('salesforce_mapped_object');
    $d8_node_mapped_object_results = $sf_mapped_object_storage->loadByProperties([
      'drupal_entity__target_type' => 'node',
      'salesforce_id' => $row->getSourceProperty('salesforce_id'),
    ]);

    if ($d8_node_mapped_object_results) {
      /** @var \Drupal\salesforce_mapping\Entity\MappedObject $d8_node_mapped_object */
      $d8_node_mapped_object = reset($d8_node_mapped_object_results);
      $row->setSourceProperty('d8_nid', $d8_node_mapped_object->getMappedEntity()->id());
    }
    else {
      // Skip this row if there is no D8 node for this Salesforce ID.
      return FALSE;
    }

    return parent::prepareRow($row);
  }

}
