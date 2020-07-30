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
 *   id = "collection_menu_grifter",
 *   label = @Translation("Section navigation"),
 *   description = @Translation("Steal the collection menu block, if displayed, and place it here."),
 *   weight = 1
 * )
 */
class CollectionMenuGrifter extends ParagraphsBehaviorBase {

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $form['info'] = [
      '#markup' => $this->t('Steal the collection menu block, if displayed, and place it here.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    $variables['attributes']['data-collection-menu-grifter'] = '1';
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraphs_entity, EntityViewDisplayInterface $display, $view_mode) {
    $build['#attached']['library'][] = 'ilr/ilr_collection_menu_grift';
  }

  /**
   * {@inheritdoc}
   *
   * This behavior is only applicable to paragraphs that are of type 'section_navigation'.
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    return $paragraphs_type->id() === 'section_navigation';
  }
}
