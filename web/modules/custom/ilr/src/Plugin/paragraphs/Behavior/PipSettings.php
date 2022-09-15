<?php

namespace Drupal\ilr\Plugin\paragraphs\Behavior;

use Drupal\paragraphs\ParagraphsBehaviorBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\image\Entity\ImageStyle;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\crop\Entity\Crop;

/**
 * Provides a Picture-in-picture plugin.
 *
 * @ParagraphsBehavior(
 *   id = "ilr_pip",
 *   label = @Translation("Pip settings"),
 *   description = @Translation("Configure picture-in-picture settings."),
 *   weight = 3
 * )
 */
class PipSettings extends ParagraphsBehaviorBase {

  /**
   * The image effect options.
   *
   * @var array
   */
  protected $alignment = [
    'left' => 'Left',
    'right' => 'Right',
  ];

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $form['alignment'] = [
      '#type' => 'radios',
      '#title' => $this->t('Align content'),
      '#options' => $this->alignment,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'alignment') ?? 'left',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    $alignment = $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'alignment') ?? 'left';
    $variables['attributes']['class'][] = 'pip--content-' . $alignment;
    $image = $variables['paragraph']->field_media->entity->field_media_image;

    // Set the background.
    $image_style = $variables['elements']['field_media'][0]['#image_style'];
    $image_style_url = ImageStyle::load($image_style)->buildUrl($image->entity->getFileUri());
    $variables['attributes']['style'][] = '--pip-background: url(' . $image_style_url . ');';

    $crop_type = \Drupal::config('focal_point.settings')->get('crop_type');
    $crop = Crop::findCrop($image->entity->getFileUri(), $crop_type);

    if ($crop) {
      $image_props = $image->first()->getValue();
      $anchor = \Drupal::service('focal_point.manager')
        ->absoluteToRelative($crop->x->value, $crop->y->value, $image_props['width'], $image_props['height']);

      $variables['attributes']['style'][] = '--cu-image-position: ' . $anchor['x'] . '% ' . $anchor['y'] . '%;';
    }
  }

  /**
   * {@inheritdoc}
   *
   * Update the view mode on entity reference fields on this paragraph
   * depending on the list style.
   */
  public function view(array &$build, Paragraph $paragraphs_entity, EntityViewDisplayInterface $display, $view_mode) {}

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(Paragraph $paragraph) {
    $summary = [];
    $summary[] = [
      'label' => $this->t('Align content'),
      'value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'alignment') ?? 'left',
    ];

    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * This behavior is only applicable to image paragraphs.
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    return in_array($paragraphs_type->id(), ['picture_in_picture']);
  }

}
