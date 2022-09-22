<?php

namespace Drupal\ilr\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\media\Entity\Media;

class ImageMedia extends Media {

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    $this->updateThumbnail();
  }

}
