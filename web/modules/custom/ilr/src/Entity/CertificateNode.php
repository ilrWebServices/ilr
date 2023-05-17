<?php

namespace Drupal\ilr\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\node\Entity\Node;

class CertificateNode extends Node implements CertificateNodeInterface {

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    if ($this->isNew() && $this->hasField('field_sf_title') && !$this->field_sf_title->isEmpty()) {
      $this->title = $this->field_sf_title->value;
    }
  }

}
