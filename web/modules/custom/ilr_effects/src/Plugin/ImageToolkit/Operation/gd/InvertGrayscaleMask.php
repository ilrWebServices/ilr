<?php

namespace Drupal\ilr_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD Watermark operation.
 *
 * @ImageToolkitOperation(
 *   id = "ilr_gd_invert_grayscale_mask",
 *   toolkit = "gd",
 *   operation = "invert_grayscale_mask",
 *   label = @Translation("Invert grayscale mask"),
 *   description = @Translation("Make pure white pixels transparent.")
 * )
 */
class InvertGrayscaleMask extends GDImageToolkitOperationBase {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    // This operation does not use any parameters.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    if ($this->getToolkit()->getType() === IMAGETYPE_PNG && $this->hasAlpha($this->getToolkit()->getResource())) {
      return TRUE;
    }

    $w = $this->getToolkit()->getWidth();
    $h = $this->getToolkit()->getHeight();
    $data = [
      'width' => $w,
      'height' => $h,
      'is_temp' => TRUE,
    ];

    // Preserves original resource, to be destroyed upon success.
    $original_resource = $this->getToolkit()->getResource();

    if (!$this->getToolkit()->apply('create_new', $data)) {
      // In case of failure, destroy the temporary resource and restore
      // the original one.
      imagedestroy($this->getToolkit()->getResource());
      $this->getToolkit()->setResource($original_resource);
      return FALSE;
    }

    // Force a transparent color fill to prevent JPEG to end up as a white
    // mask, while in memory.
    imagefill($this->getToolkit()->getResource(), 0, 0, imagecolorallocatealpha($this->getToolkit()->getResource(), 0, 0, 0, 127));

    // Create a copy of the image (if it's not too large).
    $mask = imagecreatetruecolor($w, $h);
    imagecopy($mask, $original_resource, 0, 0, 0, 0, $w, $h);

    // Set the copy to grayscale and invert it to make it a mask.
    imagefilter($mask, IMG_FILTER_GRAYSCALE);
    imagefilter($mask, IMG_FILTER_NEGATE);

    // $this->getToolkit()->setResource($mask);
    // return true;

    // Perform pixel-based alpha map application.
    for ($x = 0; $x < $w; $x++) {
      for ($y = 0; $y < $h; $y++) {
        $alpha = imagecolorsforindex($mask, imagecolorat($mask, $x, $y));
        $alpha = 127 - floor($alpha['red'] / 2);
        $color = imagecolorsforindex($this->getToolkit()->getResource(), imagecolorat($original_resource, $x, $y));
        imagesetpixel($this->getToolkit()->getResource(), $x, $y, imagecolorallocatealpha($this->getToolkit()->getResource(), $color['red'], $color['green'], $color['blue'], $alpha));
      }
    }

    // Destroy original picture and mask.
    imagedestroy($original_resource);
    imagedestroy($mask);

    return TRUE;
  }

  /**
   * Determines if an image resource has an alpha channel.
   *
   * @param \GdImage $imgdata
   * @param int $imagetype
   *
   * @return boolean
   *
   * @see https://stackoverflow.com/a/29396560
   */
  protected function hasAlpha($imgdata) {
    $w = imagesx($imgdata);
    $h = imagesy($imgdata);

    // Resize the image to save processing if larger than 50px.
    if ($w > 50 || $h > 50) {
      $thumb = imagecreatetruecolor(50, 50);
      imagealphablending($thumb, FALSE);
      imagecopyresized($thumb, $imgdata, 0, 0, 0, 0, 50, 50, $w, $h);
      $imgdata = $thumb;
      imagedestroy($thumb);
      $w = imagesx($imgdata);
      $h = imagesy($imgdata);
    }

    // Scan all pixels until a transparent pixel is found.
    for ($i = 0; $i < $w; $i++) {
      for ($j = 0; $j < $h; $j++) {
        $rgba = imagecolorat($imgdata, $i, $j);

        if (($rgba & 0x7F000000) >> 24) {
          return TRUE;
        }
      }
    }

    return FALSE;
}

}
