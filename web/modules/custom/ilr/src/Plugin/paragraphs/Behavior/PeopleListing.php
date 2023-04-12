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
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    $paragraph = $variables['paragraph'];
    $list_style = $paragraph->getBehaviorSetting('list_styles', 'list_style') ?? 'grid';

    foreach ($variables['content']['field_people']['#items'] ?? [] as $key => $persona) {
      $variables['content']['field_people'][$key]['#view_mode'] = $this->getViewModeForListStyle($list_style);
    }
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
   * Get a persona view mode for a given list style.
   *
   * @param string $list_style
   *   One of the list style machine names from this::list_styles.
   *
   * @return string
   *   A node view mode.
   */
  protected function getViewModeForListStyle($list_style) {
    switch ($list_style) {
      case 'grid-compact':
        return 'teaser_compact';

      case 'list-compact':
        return 'mini';

      default:
        return 'teaser';
    }
  }

}
