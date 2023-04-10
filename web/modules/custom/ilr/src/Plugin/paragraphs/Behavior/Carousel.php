<?php

namespace Drupal\ilr\Plugin\paragraphs\Behavior;

use Drupal\paragraphs\ParagraphsBehaviorBase;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;

/**
 * Provides a Carousel paragraph behavior plugin.
 *
 * @ParagraphsBehavior(
 *   id = "ilr_carousel",
 *   label = @Translation("Carousel"),
 *   description = @Translation("Preprocess Carousel paragraphs."),
 *   weight = 3
 * )
 */
class Carousel extends ParagraphsBehaviorBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $media */
    $media = $variables['paragraph']->field_carousel_items;

    /** @var \Drupal\media\Entity\Media $media_entity */
    foreach ($media->referencedEntities() as $key => $media_entity) {
      foreach (['field_media_oembed_video', 'field_media_image'] as $media_field) {
        if ($media_entity->hasField($media_field)) {
          $variables['carousel_items'][$key] = $media_entity->$media_field->view('minimal');
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraphs_entity, EntityViewDisplayInterface $display, $view_mode) {}

  /**
   * {@inheritdoc}
   *
   * This behavior is only applicable to carousel paragraphs.
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    return in_array($paragraphs_type->id(), ['carousel']);
  }

}
