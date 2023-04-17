<?php

namespace Drupal\ilr\Plugin\paragraphs\Behavior;

use Drupal\paragraphs\ParagraphsBehaviorBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Component\Serialization\Json;

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
   * The content placement options.
   *
   * @var array
   */
  protected $contentPlacementOptions = [
    'pinned' => 'Pinned top and bottom',
    'pinned-top' => 'Pinned top',
    'pinned-bottom' => 'Pinned bottom',
  ];

  /**
   * The layout options.
   *
   * @todo
   *   - Migrate existing `popout` content_placement settings to the new layout
   *     setting.
   *   - Rework the CSS to use better class names
   *
   *
   * @var array
   */
  protected $layoutOptions = [
    'panel-left' => 'Panel left (content left / image right)',
    'panel' => 'Panel right (content right / image left)',
    'cinematic-reversed' => 'Cinematic top (content top / image bottom)',
    'cinematic' => 'Cinematic bottom (content bottom / image top)',
    'inset' => 'Content inset',
    'popout' => 'Popout right',
    'popout-left' => 'Popout left',
    'stacked' => 'Stacked (image top / content bottom)',
    'promo' => 'Text over image (legacy)',
  ];

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    // There is no #parents key in $form, but this may be OK hardcoded.
    $parents = $form['#parents'];
    $parents_input_name = array_shift($parents);
    $parents_input_name .= '[' . implode('][', $parents) . ']';

    $form['layout'] = [
      '#type' => 'select',
      '#title' => $this->t('Layout'),
      '#description' => $this->t('The card layout.'),
      '#options' => $this->layoutOptions,
      '#required' => TRUE,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'layout') ?? array_key_first($this->layoutOptions),
    ];

    $form['use_media_aspect'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Preserve media aspect ratio'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'use_media_aspect') ?? FALSE,
      '#description' => $this->t('Generally, allowing the selected layout to determine its height is best. However, in some cases, this setting allows the card to display the entire media element.'),
    ];

    $form['content_placement'] = [
      '#type' => 'select',
      '#title' => $this->t('Content placement'),
      '#description' => $this->t('Where to place content within the content area of the card. "Pinned top and bottom" will pin all content to the bottom with the exeption of the first item, which will be pinned to the top.'),
      '#options' => $this->contentPlacementOptions,
      '#required' => FALSE,
      '#empty_option' => $this->t('- Centered -'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'content_placement') ?? '',
      '#states' => [
        'visible' => [
          ':input[name="' . $parents_input_name . '[layout]"]' => [
            ['value' => 'promo'],
          ],
        ],
      ],
    ];

    $form['media_overlay_opacity'] = [
      '#type' => 'range',
      '#min' => 1,
      '#max' => 100,
      '#step' => 10,
      '#title' => $this->t('Media overlay opacity'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'media_overlay_opacity') ?? '50',
      '#description' => $this->t('Select a range from transparent to fully opaque.'),
      '#states' => [
        'visible' => [
          ':input[name="' . $parents_input_name . '[layout]"]' => [
            ['value' => 'promo'],
          ],
        ],
      ],
    ];

    $form['use_modal_link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open link in modal'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'use_modal_link') ?? FALSE,
      '#description' => $this->t('This opens the Link URL in a modal window if it is an internal URL (i.e. another page on this site).'),
      '#access' => $paragraph->hasField('field_link'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $parent = $paragraph->getParentEntity();

    if (!$parent) {
      return;
    }

    // Prevent someone from choosing inappropriate card layouts inside decks.
    if ($parent->bundle() === 'deck' && !in_array($form_state->getValue('layout'), ['promo', 'inset', 'stacked'])) {
      $form_state->setError($form['layout'], $this->t('Sorry, but the selected layout cannot be used in card decks. Please choose a different option.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    $overlay_opacity = $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'media_overlay_opacity') ?? 50;
    $content_placement = $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'content_placement');
    $layout = $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'layout');
    $has_media = !$variables['paragraph']->field_media->isEmpty();
    $use_modal_link = $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'use_modal_link') && !$variables['paragraph']->field_link->isEmpty() && $variables['paragraph']->field_link->first()->getUrl()->isRouted();
    $variables['attributes']['style'][] = '--cu-overlay-opacity: ' . $overlay_opacity / 100 . ';';

    if ($layout && $layout !== 'promo') {
      // This assumes that any layout with a dash (`-`) is a modification of a
      // base layout. E.g. `.cu-card--panel-left` should also have
      // `.cu-card--panel`.
      if ($layout_type = substr($layout, 0, strpos($layout, '-'))) {
        $variables['attributes']['class'][] = 'cu-card--' . $layout_type;
      }

      $variables['attributes']['class'][] = 'cu-card--' . $layout;
    }
    else {
      $variables['attributes']['class'][] = 'cu-card--promo';

      // Set the button class attribute for legacy promos.
      $variables['button_attributes'] = new Attribute();

      if ($has_media || $variables['paragraph']->getBehaviorSetting('ilr_color', 'color_scheme') === 'vibrant') {
        $variables['button_attributes']->setAttribute('class', ['cu-button--overlay']);
      }

      if ($has_media) {
        $variables['attributes']['class'][] = 'cu-card--promo-with-media';
      }
    }

    if ($has_media) {
      switch ($layout) {
        case 'panel':
        case 'panel-left':
          $image_style = 'large_6_5';
          break;
        case 'cinematic':
        case 'cinematic-reversed':
          $image_style = 'large_21_9_1200x514_';
          break;
        case 'popout':
        case 'popout-left':
          $image_style = 'large_2_1';
          break;
        case 'promo':
        case 'inset':
          $image_style = 'large_preserve_aspect';
          break;
        default:
          // Use what is in the display configuration.
          $image_style = $variables['content']['field_media'][0]['#image_style'];
      }

      if ($variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'use_media_aspect')) {
        $variables['attributes']['class'][] = 'cu-card--use-aspect-ratio';
        $image_style = 'large_preserve_aspect';
      }

      $variables['content']['field_media'][0]['#image_style'] = $image_style;
    }

    if ($content_placement && $layout === 'promo') {
      $variables['attributes']['class'][] = 'cu-card--' . $content_placement;
    }

    if ($use_modal_link) {
      $variables['url_attributes'] = new Attribute();
      $variables['url_attributes']->setAttribute('class', ['use-ajax', 'cu-link--modal']);
      $variables['url_attributes']->setAttribute('data-dialog-type', 'modal');
      $variables['url_attributes']->setAttribute('data-dialog-options', Json::encode([
        'width' => 800,
        'classes' => ['ui-dialog' => 'cu-modal'],
      ]));
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
    $summary = [];

    if ($paragraph->getBehaviorSetting($this->getPluginId(), 'use_media_aspect')) {
      $summary[] = [
        'label' => $this->t('Aspect ratio'),
        'value' => 'preserved',
      ];
    }

    if ($layout = $paragraph->getBehaviorSetting($this->getPluginId(), 'layout')) {
      $summary[] = [
        'label' => $this->t('Layout'),
        'value' => $this->layoutOptions[$layout],
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
