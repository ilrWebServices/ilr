<?php

namespace Drupal\ilr\Plugin\paragraphs\Behavior;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;

/**
 * Provides a Post Listing plugin.
 *
 * @ParagraphsBehavior(
 *   id = "evergreen",
 *   label = @Translation("Evergreen"),
 *   description = @Translation("Options for evergreen content."),
 *   weight = 4
 * )
 */
class Evergreen extends ParagraphsBehaviorBase {

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $form['evergreen'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Evergreen'),
      '#description' => $this->t('Hide dates on posts in this listing. This setting is redundant if this listing is displayed in a Collection that suppresses the date display.'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'evergreen') ?? 0,
    ];

    $form['#weight'] = 10;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    if ($variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'evergreen')) {
      $variables['attributes']['class'][] = 'content--evergreen';
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

    if ($paragraph->getBehaviorSetting($this->getPluginId(), 'evergreen')) {
      $summary[] = [
        'label' => 'Evergreen',
        'value' => '✓',
      ];
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * This behavior is only applicable to postilicious listings.
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    return in_array($paragraphs_type->id(), [
      'simple_collection_listing',
      'curated_post_listing',
    ]);
  }

}
