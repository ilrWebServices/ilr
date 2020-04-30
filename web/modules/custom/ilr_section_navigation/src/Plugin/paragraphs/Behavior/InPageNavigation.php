<?php

namespace Drupal\ilr_section_navigation\Plugin\paragraphs\Behavior;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;
use Drupal\Component\Utility\Html;

/**
 * Provides a paragraph behavior for in-page navigation.
 *
 * @ParagraphsBehavior(
 *   id = "in_page_nav",
 *   label = @Translation("Allow in-page navigation"),
 *   description = @Translation("Editors can choose to enable an in-page link to this paragraph type. See InPageNavigation::showBehaviorForm()."),
 *   weight = 1
 * )
 */
class InPageNavigation extends ParagraphsBehaviorBase {

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    if (!$this->showBehaviorForm($paragraph)) {
      return $form;
    }

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => t('Link title'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'title'),
      '#maxlength' => '50',
      '#description' => t('Optional. Adding a title here will create in-page navigation to this content.'),
    ];

    return $form;
  }

  /**
   * We currently only allow in page links on blog collections.
   */
  protected function showBehaviorForm(Paragraph $paragraph) {
    if ($parent_entity = $paragraph->getParentEntity()) {
      return $parent_entity->getEntityTypeId() === 'collection' && $parent_entity->bundle() === 'blog';
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    if ($variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'title')) {
      $variables['attributes']['id'] = [$this->getFragment($variables['paragraph'])];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFragment(Paragraph $paragraph) {
    if ($title_value = $paragraph->getBehaviorSetting($this->getPluginId(), 'title')) {
      return Html::cleanCssIdentifier(strtolower($title_value));
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

    if ($title_value = $paragraph->getBehaviorSetting($this->getPluginId(), 'title')) {
      $summary[] = [
        'label' => 'Link title',
        'value' =>  $title_value,
      ];
    }

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
