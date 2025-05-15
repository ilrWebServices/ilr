<?php

namespace Drupal\ilr\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class IlrHooks {

  use StringTranslationTrait;

  #[Hook('node_presave')]
  public function populateRepresentativeImage(ContentEntityInterface $entity) {
    if ($entity->bundle() !== 'post') {
      return;
    }

    if (!$entity->hasField('field_featured_media') || !$entity->hasField('field_representative_image')) {
      return;
    }

    if (!$entity->field_featured_media->isEmpty() && $entity->field_featured_media->entity && $entity->field_featured_media->entity->bundle() === 'image' && $entity->field_representative_image->isEmpty()) {
      $entity->field_representative_image = $entity->field_featured_media;
    }
  }

  #[Hook('node_insert')]
  #[Hook('node_update')]
  public function missingRepresetativeImageNotice(ContentEntityInterface $entity) {
    if ($entity->hasField('field_representative_image') && $entity->field_representative_image->isEmpty()) {
      $message = $this->t('This %entity_type is missing a representative image. While not required, please note that this will prevent the content from appearing in some listings and will behave unpredictably in search results and social sharing links.', [
        '%entity_type' => $entity->type->entity->label()
      ]);
      \Drupal::messenger()->addWarning($message);
    }
  }

}
