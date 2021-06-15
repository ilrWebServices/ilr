<?php

namespace Drupal\ilr\Plugin\paragraphs\Behavior;

use Drupal\paragraphs\ParagraphsBehaviorBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;

/**
 * Provides a Card paragraph behavior plugin.
 *
 * @ParagraphsBehavior(
 *   id = "ilr_card",
 *   label = @Translation("Card settings"),
 *   description = @Translation("Configure Card media opacity and other settings."),
 *   weight = 3
 * )
 */
class CardSettings extends ParagraphsBehaviorBase {

  /**
   * The list style options.
   *
   * @var array
   */
  protected $contentPlacementOptions = [
    '' => 'Centered',
    'pinned' => 'Pinned top and bottom',
    'pinned-top' => 'Pinned top',
    'pinned-bottom' => 'Pinned bottom',
    'popout-right' => 'Popout right',
    'popout-left' => 'Popout left',
  ];

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $form['media_overlay_opacity'] = [
      '#type' => 'range',
      '#min' => 1,
      '#max' => 100,
      '#step' => 10,
      '#title' => $this->t('Media overlay opacity'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'media_overlay_opacity') ?? '50',
      '#description' => $this->t('Select a range from transparent to fully opaque.'),
    ];

    $form['content_placement'] = [
      '#type' => 'select',
      '#title' => $this->t('Content placement'),
      '#description' => $this->t('Where to place card content within the card, over the media if set. "Pinned top and bottom" will pin all content to the bottom with the exeption of the first item, which will be pinned to the top.'),
      '#options' => $this->contentPlacementOptions,
      '#required' => FALSE,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'content_placement') ?? reset($this->contentPlacementOptions),
    ];

    // @todo: disable if the chosen content placement is a popout.
    $form['use_media_aspect'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Preserve media aspect ratio'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'use_media_aspect') ?? FALSE,
      '#description' => $this->t('Generally, allowing the content of the card to determine its height is best. However, in some cases (such as a portait image), this setting allows the card to display the entire media element. Note, too, that this may impact the layout when set within a card deck.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    if (strpos($form_state->getValue('content_placement'), 'popout') !== FALSE && $parent = $paragraph->getParentEntity()) {
      if ($parent->bundle() === 'deck') {
        $form_state->setError($form['content_placement'], $this->t('Sorry, but popouts cannot be used in card decks. Please choose a different content placement option.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    $overlay_opacity = $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'media_overlay_opacity') ?? 50;
    $content_placement = $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'content_placement');
    $has_media = !$variables['paragraph']->field_media->isEmpty();
    $variables['attributes']['style'][] = '--cu-overlay-opacity: ' . $overlay_opacity / 100 . ';';
    $is_promo = TRUE;

    if ($content_placement) {
      $variables['attributes']['class'][] = 'cu-card--' . $content_placement;

      if (strpos($content_placement, 'popout') === 0) {
        $is_promo = FALSE;
        $variables['attributes']['class'][] = 'cu-card--popout';

        if ($has_media) {
          $variables['content']['field_media'][0]['#image_style'] = 'large_8_5';
        }
      }
    }

    if ($is_promo) {
      $variables['attributes']['class'][] = 'cu-card--promo';

      if ($has_media) {
        $variables['attributes']['class'][] = 'cu-card--promo-with-media';

        if ($variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'use_media_aspect')) {
          $variables['attributes']['class'][] = 'cu-card--use-aspect-ratio';
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
   */
  public function settingsSummary(Paragraph $paragraph) {
    $overlay_opacity = $paragraph->getBehaviorSetting($this->getPluginId(), 'media_overlay_opacity') ?? 50;

    $summary = [];
    $summary[] = [
      'label' => $this->t('Overlay'),
      'value' => $overlay_opacity . '%',
    ];

    if ($paragraph->getBehaviorSetting($this->getPluginId(), 'use_media_aspect')) {
      $summary[] = [
        'label' => $this->t('Aspect ratio'),
        'value' => 'preserved',
      ];
    }

    if ($content_placement = $paragraph->getBehaviorSetting($this->getPluginId(), 'content_placement')) {
      $summary[] = [
        'label' => $this->t('Placement'),
        'value' => $this->contentPlacementOptions[$content_placement],
      ];
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * This behavior is only applicable to promo (Card) paragraphs.
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    return in_array($paragraphs_type->id(), ['promo']);
  }

}
