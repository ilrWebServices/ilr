<?php

namespace Drupal\ilr\Plugin\paragraphs\Behavior;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Media deck plugin.
 *
 * @ParagraphsBehavior(
 *   id = "media_deck",
 *   label = @Translation("Media deck"),
 *   description = @Translation("Configure media deck settings."),
 *   weight = 1
 * )
 */
class MediaDeck extends ParagraphsBehaviorBase {

  /**
   * The deck style options.
   */
  protected $deckStyles = [
    'portrait_right' => 'Portrait right',
    'portrait_left' => 'Portrait left',
  ];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_field.manager'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $form['deck_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Deck style'),
      '#description' => $this->t('The style determines which side the portrait image displays.'),
      '#options' => $this->deckStyles,
      '#required' => TRUE,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'deck_style') ?? $this->defaultConfiguration()['deck_style'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'deck_style' => 'portrait_right',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    $paragraph = $variables['paragraph'];
    $images_render_array = [];

    if ($modifier_classes = $this->getClassModifiers($paragraph)) {
      $variables['attributes']['class'][] = $modifier_classes;
    }

    foreach ($paragraph->field_media_deck_items as $key => $item) {
      $images_render_array[$key] = [
        '#theme' => 'image_style',
        '#uri' => $item->entity->field_media_image->entity->getFileUri(),
        '#alt' => $item->entity->field_media_image->alt,
        '#style_name' => $this->getImageStyle($key),
      ];
    }

    $variables['content']['media_deck_items'] = [
      '#theme' => 'item_list__media',
      '#items' => $images_render_array,
      '#context' => ['paragraph' => $variables['paragraph']],
    ];
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

    $deck_style_setting = $paragraph->getBehaviorSetting($this->getPluginId(), 'deck_style') ?? $this->defaultConfiguration()['deck_style'];

    $summary[] = [
      'label' => 'Style',
      'value' => $this->deckStyles[$deck_style_setting],
    ];

    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * This behavior is only applicable to paragraphs that have a single
   * `collection` entity reference field.
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    return $paragraphs_type->id() === 'media_deck';
  }

  /**
   * Get an image style based on the key.
   *
   * @todo Consider using another settings form element to make this selectable.
   *
   * @param int $key
   *   The order placement of the media item in the deck.
   *
   * @return string
   *   An image style.
   */
  protected function getImageStyle($key) {
    $image_styles = [
      'medium_3_2',
      'medium_2_3',
      'medium_1_1',
    ];

    return $image_styles[$key];
  }

  /**
   * Get CSS classes for a given list style.
   *
   * @return string
   *   An string of class names for the deck wrapper.
   */
  protected function getClassModifiers(Paragraph $paragraph) {
    $classes = '';

    $deck_style = $paragraph->getBehaviorSetting($this->getPluginId(), 'deck_style') ?? $this->defaultConfiguration()['deck_style'];

    if ($deck_style === 'portrait_left') {
      $classes = 'paragraph--type--media-deck--portrait-left';
    }

    return $classes;
  }

}
