<?php

namespace Drupal\ilr\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\ilr\BundleFieldDefinition;
use Drupal\node\Entity\Node;
use Drupal\salesforce_mapping\Entity\MappedObjectInterface;

class ClassNode extends Node implements ClassNodeInterface {

  protected $classNodeSaleforceMappedObject = NULL;

  /**
   * {@inheritdoc}
   */
  public function getClassNodeSalesforceMappedObject(): MappedObjectInterface|FALSE {
    if (is_null($this->classNodeSaleforceMappedObject) && $this->id()) {
      $mapped_objects = $this->entityTypeManager()->getStorage('salesforce_mapped_object')
        ->loadByProperties([
          'drupal_entity__target_type' => 'node',
          'drupal_entity__target_id' => $this->id(),
          'salesforce_mapping' => 'class_node',
        ]);

      $this->classNodeSaleforceMappedObject = reset($mapped_objects);
    }

    return $this->classNodeSaleforceMappedObject ?? FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * @todo Switch to cache tags and targeted cache busting instead of this hammer.
   */
  public function getCacheMaxAge() {
    return 15 * 60;
  }

  /**
   * {@inheritdoc}
   */
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    $definitions = [];

    $definitions['ilroutreach_discount_price'] = BundleFieldDefinition::create('decimal')
      // This is essential: The array key is NOT used as the field machine
      // name, so it MUST be specified here!
      ->setName('ilroutreach_discount_price')
      // These two are essential: They define the entity type and the bundle
      // that the field is on.
      ->setTargetEntityTypeId($entity_type->id())
      ->setTargetBundle($bundle)
      // Further define the field as you would with a base field.
      ->setLabel(t('ILR outreach discount price'))
      ->setComputed(TRUE)
      ->setClass('\Drupal\ilr\ClassDiscountPriceItemList')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDescription(t('A discount price computed from an automatic discount code, if available and eligible.'))
      ->setSetting('prefix', '$')
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'number_decimal',
        'weight' => '0',
      ]);

      $definitions['ilroutreach_discount_date'] = BundleFieldDefinition::create('daterange')
        // This is essential: The array key is NOT used as the field machine
        // name, so it MUST be specified here!
        ->setName('ilroutreach_discount_date')
        // These two are essential: They define the entity type and the bundle
        // that the field is on.
        ->setTargetEntityTypeId($entity_type->id())
        ->setTargetBundle($bundle)
        // Further define the field as you would with a base field.
        ->setLabel(t('ILR outreach discount dates'))
        ->setComputed(TRUE)
        ->setClass('\Drupal\ilr\ClassDiscountDateItemList')
        ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
        ->setDescription(t('A discount start and end dates computed from an automatic discount code, if available and eligible.'))
        ->setSetting('datetime_type', 'date')
        ->setDisplayConfigurable('view', TRUE)
        ->setDisplayOptions('view', [
          'type' => 'date_range_without_time',
          'weight' => '0',
        ]);

    return $definitions;
  }

}
