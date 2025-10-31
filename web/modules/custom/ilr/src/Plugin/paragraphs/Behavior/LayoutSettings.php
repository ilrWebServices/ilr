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
 * Provides layout settings for components.
 *
 * @ParagraphsBehavior(
 *   id = "layout_settings",
 *   label = @Translation("Layout Settings"),
 *   description = @Translation("Configure layout settings for components."),
 *   weight = 2
 * )
 */
class LayoutSettings extends ParagraphsBehaviorBase {

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
      $variables['attributes']['class'][] = 'cu-layoutscheme--reversed';
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
        'label' => 'Reversed Component',
        'value' => 'True',
      ];
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * This behavior is applicable to paragraphs that support layout settings.
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    return in_array($paragraphs_type->id(), ['text_with_media']);
  }

}
