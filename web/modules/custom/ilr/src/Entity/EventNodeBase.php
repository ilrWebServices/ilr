<?php

namespace Drupal\ilr\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\ilr\BundleFieldDefinition;
use Drupal\node\Entity\Node;

abstract class EventNodeBase extends Node {

  /**
   * {@inheritdoc}
   */
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    $definitions = parent::bundleFieldDefinitions($entity_type, $bundle, $base_field_definitions);

    $definitions['event_date'] = BundleFieldDefinition::create('daterange')
      ->setName('event_date')
      ->setTargetEntityTypeId($entity_type->id())
      ->setTargetBundle($bundle)
      ->setLabel(t('Event date'))
      ->setRequired(FALSE)
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setSetting('datetime_type', 'datetime')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'daterange_default',
        'weight' => -5,
        'settings' => [
          'timezone_override' => '',
          'format_type' => 'medium',
          'separator' => '-',
        ],
      ])
      ->setDisplayOptions('form', [
        'type' => 'daterange_default',
        'weight' => -2,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['location_address'] = BundleFieldDefinition::create('address')
      ->setName('location_address')
      ->setTargetEntityTypeId($entity_type->id())
      ->setTargetBundle($bundle)
      ->setLabel(t('Location address'))
      ->setRequired(FALSE)
      ->setSettings([
        'available_countries' => [
          'US' => 'US',
        ],
        'langcode_override' => '',
        'field_overrides' => [
          'givenName' => [
            'override' => 'hidden'
          ],
          'additionalName' => [
            'override' => 'hidden'
          ],
          'familyName' => [
            'override' => 'hidden'
          ],
          'organization' => [
            'override' => 'hidden'
          ],
          'sortingCode' => [
            'override' => 'hidden'
          ],
          'dependentLocality' => [
            'override' => 'hidden'
          ],
        ],
        'fields' => [],
      ])
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'address_default',
        'weight' => -5
      ])
      ->setDisplayOptions('form', [
        'type' => 'address_default',
        'weight' => -2,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['location_link'] = BundleFieldDefinition::create('link')
      ->setName('location_link')
      ->setTargetEntityTypeId($entity_type->id())
      ->setTargetBundle($bundle)
      ->setLabel(t('Location link'))
      ->setRequired(FALSE)
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'link',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'linkit',
        'weight' => -2,
        'settings' => [
          'placeholder_url' => '',
          'placeholder_title' => '',
          'linkit_profile' => 'ilr_content',
          'linkit_auto_link_text' => TRUE,
        ],
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $definitions;
  }

}
