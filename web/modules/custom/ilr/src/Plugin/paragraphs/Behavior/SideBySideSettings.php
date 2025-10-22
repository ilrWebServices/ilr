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
 * Provides settings for side-by-side components.
 *
 * @ParagraphsBehavior(
 *   id = "side_by_side_settings",
 *   label = @Translation("Side by Side settings"),
 *   description = @Translation("Configure side-by-side component settings."),
 *   weight = 2
 * )
 */
class SideBySideSettings extends ParagraphsBehaviorBase {

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
    $form['reverse_component'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reverse Component'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'reverse_component') ?? FALSE,
      '#description' => $this->t('When checked, the image will be aligned to the left in the side-by-side layout.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    if ($variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'reverse_component')) {
      $variables['attributes']['class'][] = 'cu-side-by-side--reversed';
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

    if ($paragraph->getBehaviorSetting($this->getPluginId(), 'reverse_component')) {
      $summary[] = [
        'label' => 'Reverse Component',
        'value' => 'True',
      ];
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * This behavior is only applicable to side-by-side paragraphs.
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    return in_array($paragraphs_type->id(), ['text_with_media']);
  }

}
