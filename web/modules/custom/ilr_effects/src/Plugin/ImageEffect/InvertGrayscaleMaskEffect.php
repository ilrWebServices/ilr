<?php

namespace Drupal\ilr_effects\Plugin\ImageEffect;

use Drupal\Core\Image\ImageInterface;
use Drupal\image\ImageEffectBase;

/**
 * Makes white pixels in images transparent.
 *
 * @ImageEffect(
 *   id = "ilr_invert_grayscale_mask",
 *   label = @Translation("Invert grayscale mask"),
 *   description = @Translation("Makes all pure white pixels in the image transparent.")
 * )
 */
class InvertGrayscaleMaskEffect extends ImageEffectBase {

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    return $image->apply('invert_grayscale_mask', []);
  }

}
