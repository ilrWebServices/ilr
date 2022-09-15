<?php

namespace Drupal\ilr_effects\Plugin\paragraphs\Behavior;

use Drupal\paragraphs\ParagraphsBehaviorBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\image\Entity\ImageStyle;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\crop\Entity\Crop;

/**
 * Provides an Image effects plugin.
 *
 * @ParagraphsBehavior(
 *   id = "ilr_effects_image",
 *   label = @Translation("Image effects"),
 *   description = @Translation("Configure animation effects."),
 *   weight = 3
 * )
 */
class ImageEffects extends ParagraphsBehaviorBase {

  /**
   * The image effect options.
   *
   * @var array
   */
  protected $imageEffects = [
    'none' => 'None',
    'zoom-out' => 'Zoom Out (Ken Burns)',
    'curtain-reveal' => 'Curtain Reveal',
  ];

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $form['image_effect'] = [
      '#type' => 'select',
      '#title' => $this->t('Effect'),
      '#options' => $this->imageEffects,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'image_effect'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    if ($effect = $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'image_effect')) {
      $variables['attributes']['class'][] = 'ilr-effect-image';
      $variables['attributes']['class'][] = $effect;

      if ($effect === 'curtain-reveal') {
        $image_formatter = $variables['elements']['field_media'][0];
        /** @var \Drupal\image\Plugin\Field\FieldType\ImageItem $image */
        $image = $image_formatter['#item'];
        $image_style = $image_formatter['#image_style'];
        $image_style_url = ImageStyle::load($image_style)->buildUrl($image->entity->getFileUri());
        $variables['attributes']['style'][] = '--ilr-effects-img: url(' . $image_style_url . ');';
        $crop_type = \Drupal::config('focal_point.settings')->get('crop_type');
        $crop = Crop::findCrop($image->entity->getFileUri(), $crop_type);

        if ($crop) {
          $image_props = $image->getValue();
          $anchor = \Drupal::service('focal_point.manager')
            ->absoluteToRelative($crop->x->value, $crop->y->value, $image_props['width'], $image_props['height']);
          $variables['attributes']['style'][] = '--cu-image-position: ' . $anchor['x'] . '% ' . $anchor['y'] . '%;';
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * Update the view mode on entity reference fields on this paragraph
   * depending on the list style.
   */
  public function view(array &$build, Paragraph $paragraphs_entity, EntityViewDisplayInterface $display, $view_mode) {
    if (!$effect = $paragraphs_entity->getBehaviorSetting($this->getPluginId(), 'image_effect')) {
      return;
    }

    if ($effect !== 'none') {
      $build['#attached']['library'][] = 'ilr_effects/ilr_effects';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(Paragraph $paragraph) {
    $summary = [];
    $effect = '';

    if ($paragraph->getBehaviorSetting($this->getPluginId(), 'image_effect')) {
      $effect = $this->imageEffects[$paragraph->getBehaviorSetting($this->getPluginId(), 'image_effect')];
    }

    $summary[] = [
      'label' => 'Effect',
      'value' => $effect,
    ];

    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * This behavior is only applicable to image paragraphs.
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    return in_array($paragraphs_type->id(), ['image']);
  }

}
