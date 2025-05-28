<?php

namespace Drupal\ilr\Plugin\paragraphs\Behavior;

use Drupal\paragraphs\ParagraphsBehaviorBase;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Provides a Carousel paragraph behavior plugin.
 *
 * @ParagraphsBehavior(
 *   id = "ilr_carousel",
 *   label = @Translation("Carousel/Gallery items"),
 *   description = @Translation("Preprocess Carousel/Gallery paragraphs for media items."),
 *   weight = 3
 * )
 */
class Carousel extends ParagraphsBehaviorBase {

  /**
   * Some view modes for the media items.
   *
   * @var array
   */
  protected $viewModes = [
    'minimal' => 'Minimal',
    'gallery' => 'Gallery',
  ];

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();

    $form['media_view_mode_default'] = [
      '#type' => 'select',
      '#title' => $this->t('Default media view mode'),
      '#options' => $this->viewModes,
      '#default_value' => $config['media_view_mode_default'] ?? 'minimal',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['media_view_mode_default'] = $form_state->getValue('media_view_mode_default');
  }

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $media_view_mode_default = $this->getConfiguration()['media_view_mode_default'] ?? 'minimal';

    $form['media_view_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Media view mode'),
      '#description' => $this->t('The view mode in which to display the media items.'),
      '#options' => $this->viewModes,
      '#required' => FALSE,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'media_view_mode', $media_view_mode_default),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $media */
    $media = $variables['paragraph']->field_carousel_items;
    $media_view_mode_default = $this->getConfiguration()['media_view_mode_default'] ?? 'minimal';
    $media_view_mode = $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'media_view_mode', $media_view_mode_default);

    /** @var \Drupal\media\Entity\Media $media_entity */
    foreach ($media->referencedEntities() as $key => $media_entity) {
      foreach (['field_media_oembed_video', 'field_media_image'] as $media_field) {
        if ($media_entity->hasField($media_field)) {
          $variables['carousel_items'][$key] = $media_entity->$media_field->view($media_view_mode);
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
    return in_array($paragraphs_type->id(), ['carousel', 'gallery']);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(Paragraph $paragraph) {
    $summary = [];
    $media_view_mode_default = $this->getConfiguration()['media_view_mode_default'] ?? 'minimal';
    $media_view_mode = $paragraph->getBehaviorSetting($this->getPluginId(), 'media_view_mode', $media_view_mode_default);
    $summary[] = [
      'label' => $this->t('Media view mode'),
      'value' => $this->viewModes[$media_view_mode],
    ];
    return $summary;
  }

}
