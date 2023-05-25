<?php

namespace Drupal\ilr;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Entity reference list for a computed field that displays course_certificates for certificates.
 */
class CertificateCourseCertificateItemList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * Compute the courses that reference this certificate.
   *
   * @see CertificateNode::bundleFieldDefinitions()
   */
  protected function computeValue() {
    /** @var \Drupal\ilr\Entity\CertificateNode $certificate_entity */
    $certificate_entity = $this->getEntity();
    $course_certificates = $certificate_entity->getCourseCertificates($this->getSetting('required_status') ?? '');
    $key = 0;

    foreach ($course_certificates as $nid) {
      $this->list[$key] = $this->createItem($key, $nid);
      $key++;
    }
  }

}
