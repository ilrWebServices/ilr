<?php

namespace Drupal\ilr\Plugin\paragraphs\Behavior;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;

/**
 * Provides an admin label for sections.
 *
 * @ParagraphsBehavior(
 *   id = "admin_section_label",
 *   label = @Translation("Admin section label"),
 *   description = @Translation("Provides an admin label for sections."),
 *   weight = 1
 * )
 */
class AdminSectionLabel extends ParagraphsBehaviorBase {

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => t('Admin label'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'label'),
      '#maxlength' => '50',
      '#description' => t('Optional. Admin labels appear in the editing interface on sections without headings.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraphs_entity, EntityViewDisplayInterface $display, $view_mode) {}

  /**
   * {@inheritdoc}
   *
   * Rather than a summary, the admin label is rendered in the form element.
   * @see ilr_field_widget_paragraphs_form_alter().
   */
  public function settingsSummary(Paragraph $paragraph) {}

  /**
   * {@inheritdoc}
   *
   * This behavior is only applicable to paragraphs that are of type 'section'.
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    return $paragraphs_type->id() === 'section';
  }

}
