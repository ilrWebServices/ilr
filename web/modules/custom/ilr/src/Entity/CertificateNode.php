<?php

namespace Drupal\ilr\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\ilr\BundleFieldDefinition;
use Drupal\node\Entity\Node;

/**
 * Custom class for the `certificate` node bundle.
 */
class CertificateNode extends Node implements CertificateNodeInterface {

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    if ($this->isNew() && $this->hasField('field_sf_title') && !$this->field_sf_title->isEmpty()) {
      $this->title = $this->field_sf_title->value;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCourseCertificates($required = '') {
    $query = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', 'course_certificate')
      ->condition('field_certificate', $this->id())
      ->condition('field_course', NULL, 'IS NOT NULL')
      ->sort('field_required', 'DESC')
      ->sort('field_weight');

    if ($required) {
      $query->condition('field_required', $required);
    }

    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    $definitions = parent::bundleFieldDefinitions($entity_type, $bundle, $base_field_definitions);

    $definitions['course_certificates'] = BundleFieldDefinition::create('entity_reference')
      ->setName('course_certificates')
      ->setTargetEntityTypeId($entity_type->id())
      ->setTargetBundle($bundle)
      ->setLabel(t('Course certificates (reverse reference)'))
      ->setRevisionable(FALSE)
      ->setComputed(TRUE)
      ->setClass('\Drupal\ilr\CertificateCourseCertificateItemList')
      ->setSettings([
        'handler' => 'default:node',
        'handler_settings' => [
          'target_bundles' => [
            'course_certificate' => 'course_certificate'
          ]
        ],
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'weight' => 0,
      ]);

    return $definitions;
  }

}
