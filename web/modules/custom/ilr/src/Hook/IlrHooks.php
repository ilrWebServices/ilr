<?php

namespace Drupal\ilr\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Hook\Attribute\Hook;

class IlrHooks {

  #[Hook('node_presave')]
  public function populateRepresentativeImage(ContentEntityInterface $entity) {
    if ($entity->bundle() !== 'post') {
      return;
    }

    if (!$entity->hasField('field_featured_media') || !$entity->hasField('field_representative_image')) {
      return;
    }

    if (!$entity->field_featured_media->isEmpty() && $entity->field_featured_media->entity->bundle() === 'image' && $entity->field_representative_image->isEmpty()) {
      $entity->field_representative_image = $entity->field_featured_media->target_id;
    }
  }

}
