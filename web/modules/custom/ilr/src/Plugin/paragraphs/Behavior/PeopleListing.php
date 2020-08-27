<?php

namespace Drupal\ilr\Plugin\paragraphs\Behavior;

use Drupal\paragraphs\ParagraphsBehaviorBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;

/**
 * Provides a People listing behavior plugin.
 *
 * @ParagraphsBehavior(
 *   id = "ilr_people_listing",
 *   label = @Translation("People listing settings"),
 *   description = @Translation("Configure settings for people listings."),
 *   weight = 3
 * )
 */
class PeopleListing extends ParagraphsBehaviorBase {

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $form['columns'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of columns'),
      '#description' => $this->t('The number of columns to display on wider screens.'),
      '#min' => 2,
      '#max' => 4,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'columns') ?? 3,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    $paragraph = $variables['paragraph'];
    $list_style = $paragraph->getBehaviorSetting('list_styles', 'list_style') ?? 'grid';
    $count = 1;
    foreach ($variables['content']['field_people']['#items'] as $key => $persona) {
      $variables['content']['field_people'][$key]['#view_mode'] = $this->getViewModeForListStyle($list_style, $count);
      $count++;
    }

    $columns = $paragraph->getBehaviorSetting('ilr_people_listing', 'columns') ?? 3;
    $variables['attributes']['class'] = ['cu-grid--' . $columns . 'col'];
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraphs_entity, EntityViewDisplayInterface $display, $view_mode) {}

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(Paragraph $paragraph) {}

  /**
   * {@inheritdoc}
   *
   * This behavior is only applicable to people listing paragraphs.
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    return in_array($paragraphs_type->id(), ['people_listing']);
  }

  /**
   * Get a node view mode for a given list style.
   *
   * @param $list_style string
   *   One of the list style machine names from this::list_styles.
   *
   * @param $post_number int
   *   The order placement of the post in the listing.
   *
   * @return string
   *   A node view mode.
   */
  protected function getViewModeForListStyle($list_style, $item_position) {
    switch ($list_style) {
      case 'grid-compact':
        return 'teaser_compact';
      case 'list-compact':
        return 'mini';
      case 'grid-featured':
        return $item_position === 1 ? 'promo' : 'teaser';
      default:
        return 'teaser';
    }
  }
}
