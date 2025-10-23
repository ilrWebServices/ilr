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
 * Provides settings for components.
 *
 * @ParagraphsBehavior(
 *   id = "component_settings",
 *   label = @Translation("Component settings"),
 *   description = @Translation("Configure component settings."),
 *   weight = 1
 * )
 */
class ComponentSettings extends ParagraphsBehaviorBase {

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
    $form['remove_bottom_margin'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Remove bottom margin'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'remove_bottom_margin') ?? FALSE,
      '#description' => $this->t('This setting removes the margin between adjacent components or sections.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    if ($variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'remove_bottom_margin') ) {
      $variables['attributes']['class'][] = $variables['paragraph']->bundle() === 'section'
        ? 'cu-section--gapless'
        : 'cu-component--no-bottom-margin';
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

    if ($paragraph->getBehaviorSetting($this->getPluginId(), 'remove_bottom_margin')) {
      $summary[] = [
        'label' => 'Margin removed',
        'value' => '✓',
      ];
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * This behavior is only applicable to paragraphs of type 'deck'.
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    return in_array($paragraphs_type->id(), ['deck', 'rich_text', 'section', 'anchor_button_to_form']);
  }

}
