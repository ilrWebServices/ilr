<?php

namespace Drupal\collection\Form;

use Drupal\Core\Entity\Form\DeleteMultipleForm;
use Drupal\Core\Url;

/**
 * Provides a collection item entities deletion confirmation form.
 */
class CollectionItemDeleteMultipleForm extends DeleteMultipleForm {

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    $route_params = [];

    if (count($this->selection) > 0) {
      if ($collection_id = \Drupal::routeMatch()->getRawParameter('collection')) {
        $route_params['collection'] = $collection_id;
      }
    }

    if ($this->entityType->hasLinkTemplate('collection')) {
      return new Url('entity.' . $this->entityTypeId . '.collection', $route_params);
    }
    else {
      return new Url('<front>');
    }
  }

}
