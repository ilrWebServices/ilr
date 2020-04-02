<?php

namespace Drupal\ilr\Plugin\paragraphs\Behavior;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;

/**
 * Provides a Union section settings behavior.
 *
 * @ParagraphsBehavior(
 *   id = "union_section_settings",
 *   label = @Translation("Union section settings"),
 *   description = @Translation("Configure settings for union sections."),
 *   weight = 1
 * )
 */
class UnionSectionSettings extends ParagraphsBehaviorBase {

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $form['full_width'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Full width'),
      '#description' => $this->t('Full width sections reduce the right and left margins for this section. This is typically used to for decks, images, or videos as a way of creating better visual flow down the page.'),
      '#min' => 1,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'full_width'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    // Check the behavior settings and set the class modifier if full width.
    if ($variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'full_width')) {
      $variables['attributes']['class'] = ['cu-section--wide'];
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
    $full_width = $paragraph->getBehaviorSetting($this->getPluginId(), 'full_width');

    $summary[] = [
      'label' => 'Full width',
      'value' =>  ($full_width) ? 'yes' : 'no',
    ];

    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * This behavior is only applicable to paragraphs that are of type 'section'.
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    return $paragraphs_type->id() === 'section';
  }
}
