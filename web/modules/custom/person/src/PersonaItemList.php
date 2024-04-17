<?php

namespace Drupal\person;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Entity reference list for a computed field that displays course_certificates for certificates.
 */
class PersonaItemList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * Compute the courses that reference this certificate.
   *
   * @see CertificateNode::bundleFieldDefinitions()
   */
  protected function computeValue() {
    /** @var \Drupal\person\Entity\Person $person */
    $person = $this->getEntity();
    $personas = $person->getPersonas();
    $key = 0;

    foreach ($personas as $pid) {
      $this->list[$key] = $this->createItem($key, $pid);
      $key++;
    }
  }

}
