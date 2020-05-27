<?php

namespace Drupal\ilr\Plugin\paragraphs\Behavior;

use Drupal\paragraphs\ParagraphsBehaviorBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\image\Entity\ImageStyle;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;

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

    // Set the background.
    $image_style = $variables['elements']['field_media'][0]['#image_style'];
    $image_style_url = ImageStyle::load($image_style)->buildUrl($variables['paragraph']->field_media->entity->field_media_image->entity->getFileUri());
    $variables['attributes']['style'] = '--pip-background: url(' . $image_style_url . ')';
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
