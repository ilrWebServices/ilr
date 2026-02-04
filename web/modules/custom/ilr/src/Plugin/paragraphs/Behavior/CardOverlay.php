<?php

namespace Drupal\ilr\Plugin\paragraphs\Behavior;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;

/**
 * Provides settings for the Card Overlay component.
 *
 * @ParagraphsBehavior(
 *   id = "card_overlay",
 *   label = @Translation("Card overlay settings"),
 *   description = @Translation("Configure card overlay settings."),
 *   weight = 1
 * )
 */
class CardOverlay extends ParagraphsBehaviorBase {

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $form['expand_on_hover'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expand card content on hover'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'expand_on_hover') ?? FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    if ($variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'expand_on_hover')) {
      $variables['attributes']['class'][] = 'cu-card-overlay--hover-expand';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraphs_entity, EntityViewDisplayInterface $display, $view_mode) {}

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    return $paragraphs_type->id() === 'card_overlay';
  }

}
