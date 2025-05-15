<?php

namespace Drupal\ilr\Hook;

use Drupal\Core\Cache\Cache;
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

    if (!$entity->field_featured_media->isEmpty() && $entity->field_featured_media->entity->bundle() === 'image' && $entity->field_representative_image->isEmpty()) {
      $entity->field_representative_image = $entity->field_featured_media->target_id;
    }
  }

  /**
   * @deprecated
   */
  #[Hook('node_presave')]
  public function calculateCertificateInfo(ContentEntityInterface $entity) {
    if ($entity->bundle() === 'certificate') {
      $certificate_total = 0;
      $certificate_topics = [];

      // Deduce the total cost and topic terms of the certificate from its courses
      // (and classes).
      foreach ($entity->field_course->referencedEntities() as $course) {
        // Since `classes` is a computed field, and it is sorted by upcoming class
        // dates, using `first()` will ensure that the next upcoming class will be
        // used for the course price.
        if ($course->classes->count()) {
          $certificate_total += $course->classes->first()->entity->field_price->value;
        }

        // Add all unique topics for each course to the topics for certificate.
        foreach ($course->field_topics->referencedEntities() as $topic) {
          if (!in_array($topic->id(), $certificate_topics)) {
            $certificate_topics[] = $topic->id();
          }
        }
      }

      $entity->set('field_total_cost', $certificate_total);
      $entity->set('field_topics', $certificate_topics);
    }
  }

  #[Hook('node_presave')]
  public function clearCourseCacheForClass(ContentEntityInterface $entity) {
    if ($entity->bundle() === 'class' && !$entity->field_course->isEmpty()) {
      Cache::invalidateTags($entity->field_course->entity->getCacheTagsToInvalidate());
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
