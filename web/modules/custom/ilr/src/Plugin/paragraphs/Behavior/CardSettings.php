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
    'popout' => 'Popout right',
    'popout-left' => 'Popout left',
    'promo' => 'Text over image (legacy)',
  ];

  /**
   * The background variants.
   *
   * @var array
   */
  protected $bgOptions = [
    'light' => 'Light',
    'dark' => 'Dark',
    'vibrant' => 'Vibrant',
  ];

  /**
   * The icon options.
   *
   * @var array
   */
  protected $iconOptions = [
    'check-circle' => 'check-circle',
    'cornell-seal' => 'cornell-seal',
    'facebook' => 'facebook',
    'handshake' => 'handshake',
    'ilr-nickname' => 'ilr-nickname',
    'instagram' => 'instagram',
    'linkedin' => 'linkedin',
    'mortarboard' => 'mortarboard',
    'news-phone' => 'news-phone',
    'newsletter' => 'newsletter',
    'speech-bubble' => 'speech-bubble',
    'student' => 'student',
    'profile' => 'profile',
    'tower' => 'tower',
    'twitter' => 'twitter',
    'play' => 'play',
    'pause' => 'pause',
    'youtube' => 'youtube',
  ];

  /**
   * The icon placement options.
   *
   * @var array
   */
  protected $iconPlacementOptions = [
    'inline' => 'Inline',
    'inline-centered' => 'Inline-centered',
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

    $form['bg_color'] = [
      '#type' => 'select',
      '#title' => $this->t('Background'),
      '#description' => $this->t('The background color for this card. This is only visible in cards where the text does not cover the media.'),
      '#options' => $this->bgOptions,
      '#required' => FALSE,
      '#empty_option' => $this->t('- Default (generally transparent) -'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'bg_color') ?? '',
    ];

    $form['content_placement'] = [
      '#type' => 'select',
      '#title' => $this->t('Content placement'),
      '#description' => $this->t('Where to place content within the content area of the card. "Pinned top and bottom" will pin all content to the bottom with the exeption of the first item, which will be pinned to the top.'),
      '#options' => $this->contentPlacementOptions,
      '#required' => FALSE,
      '#empty_option' => $this->t('- Centered -'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'content_placement') ?? '',
    ];

    $form['icon'] = [
      '#type' => 'select',
      '#title' => $this->t('Icon'),
      '#description' => $this->t('An optional icon to add to the top of the content area in the card.'),
      '#options' => $this->iconOptions,
      '#required' => FALSE,
      '#empty_option' => $this->t('- Select -'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'icon') ?? '',
    ];

    $form['icon_label'] = [
      '#type' => 'textfield',
      '#title' => t('Icon label'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'icon_label'),
      '#maxlength' => '50',
      '#description' => t('Optional. This text will appear below or next to the icon.'),
      '#states' => [
        'invisible' => [
          ':input[name="' . $parents_input_name . '[icon]"]' => [
            ['value' => ''],
          ],
        ],
      ],

    ];

    $form['icon_placement'] = [
      '#type' => 'select',
      '#title' => t('Icon placement'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'icon_placement') ?? array_key_first($this->iconPlacementOptions),
      '#options' => $this->iconPlacementOptions,
      '#empty_option' => $this->t('Centered (default)'),
      // '#description' => t('Optional. This text will appear below or next to the icon.'),
      '#states' => [
        'invisible' => [
          ':input[name="' . $parents_input_name . '[icon]"]' => [
            ['value' => ''],
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
    ];

    // @todo: disable if the chosen content placement is a popout.
    $form['use_media_aspect'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Preserve media aspect ratio'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'use_media_aspect') ?? FALSE,
      '#description' => $this->t('Generally, allowing the content of the card to determine its height is best. However, in some cases (such as a portait image), this setting allows the card to display the entire media element. Note, too, that this may impact the layout when set within a card deck.'),
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
   *
   * @todo Figure out whether we need to prevent someone from choosing both
   * content placement and layout (e.g. Content placement options can only go
   * with the `legacy` layout)
   */
  public function validateBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $parent = $paragraph->getParentEntity();

    if (!$parent) {
      return;
    }

    if ($parent->bundle() === 'deck' && (strpos($form_state->getValue('content_placement'), 'popout') !== FALSE || $form_state->getValue('layout') !== 'promo' )) {
      $form_state->setError($form['content_placement'], $this->t('Sorry, but the selected layout cannot be used in card decks. Please choose a different option.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    $overlay_opacity = $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'media_overlay_opacity') ?? 50;
    $content_placement = $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'content_placement');
    $layout = $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'layout');
    $bg_color = $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'bg_color');
    $has_media = !$variables['paragraph']->field_media->isEmpty();
    $use_modal_link = $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'use_modal_link') && !$variables['paragraph']->field_link->isEmpty() && $variables['paragraph']->field_link->first()->getUrl()->isRouted();
    $variables['attributes']['style'][] = '--cu-overlay-opacity: ' . $overlay_opacity / 100 . ';';

    if ($layout && $layout !== 'promo') {
      $layout_type = substr($layout, 0, strpos($layout, '-'));
      $variables['attributes']['class'][] = 'cu-card--' . $layout_type;
      $variables['attributes']['class'][] = 'cu-card--' . $layout;

      if ($has_media) {
        $variables['content']['field_media'][0]['#image_style'] = 'large_8_5';
      }
    }
    else {
      $variables['attributes']['class'][] = 'cu-card--promo';

      // Set the button class attribute for legacy promos.
      $variables['button_attributes'] = new Attribute();
      $variables['button_attributes']->setAttribute('class', ['cu-button--overlay']);

      if ($has_media) {
        $variables['attributes']['class'][] = 'cu-card--promo-with-media';

        if ($variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'use_media_aspect')) {
          $variables['attributes']['class'][] = 'cu-card--use-aspect-ratio';
        }
      }
    }

    if ($content_placement) {
      $variables['attributes']['class'][] = 'cu-card--' . $content_placement;
    }

    if ($bg_color) {
      $variables['attributes']['class'][] = 'cu-card--' . $bg_color;
    }

    if ($icon = $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'icon')) {
      $variables['paragraph']->cu_icon = [
        'title' => $icon,
        'icon' => $icon,
      ];

      if ($icon_placement = $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'icon_placement')) {
        $variables['paragraph']->cu_icon['attributes'] = ['class' => 'cu-icon--' . $icon_placement];
      }

      if ($label = $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'icon_label')) {
        $variables['paragraph']->cu_icon['label'] = $label;
        $variables['paragraph']->cu_icon['title'] = $label;
      }
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

    if ($bg_color = $paragraph->getBehaviorSetting($this->getPluginId(), 'bg_color')) {
      $summary[] = [
        'label' => $this->t('Background'),
        'value' => $this->bgOptions[$bg_color],
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
