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
    $host_entity = $form_state->getBuildInfo()['callback_object']->getEntity();
    $extra_field_defs = \Drupal::service('plugin.manager.extra_field_display')->fieldInfo();

    // There is no #parents key in $form, but this may be OK hardcoded.
    $parents = $form['#parents'];
    $parents_input_name = array_shift($parents);
    $parents_input_name .= '[' . implode('][', $parents) . ']';

    $form['fragment'] = [
      '#type' => 'textfield',
      '#title' => t('Link fragment'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'fragment'),
      '#maxlength' => '50',
      '#description' => $this->t('Optional. This will become the <code>id</code> attribute for this section, which will allow it to be used as an in-page anchor, e.g. <code>/this/page#fragment</code>. Only lowercase letters, numbers, dashes, and underscores are valid.'),
    ];

    // The title value is only displayed if the host entity has the
    // `ilr_section_navigation` extra field enabled.
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => t('Link title'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'title'),
      '#maxlength' => '50',
      '#description' => $this->t('Optional. This title will be displayed on the page as an in-page link to this section, using the link fragment above.'),
      '#access' => isset($extra_field_defs[$host_entity->getEntityTypeId()][$host_entity->bundle()]['display']['extra_field_ilr_section_navigation']),
      '#states' => [
        'invisible' => [
          ':input[name="' . $parents_input_name . '[fragment]"]' => [
            ['value' => ''],
          ],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $entered_fragment = $form_state->getValue('fragment');
    $css_cleaned_fragment = Html::cleanCssIdentifier($entered_fragment);

    if ($entered_fragment !== $css_cleaned_fragment) {
      $form_state->setError($form['fragment'], $this->t('Link fragment should only contain lowercase letters, numbers, dashes, and underscores, and should not start with a number or dash. Spaces aren\'t allowed, either. Try <strong><code>@example</code></strong> instead.', [
        '@example' => $css_cleaned_fragment,
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    if ($fragment = $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'fragment')) {
      $variables['attributes']['id'] = [$fragment];
    }
    else {
      $variables['attributes']['id'] = ['section-' . $variables['paragraph']->id()];
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
        'value' => $title_value,
      ];
    }

    if ($fragment_value = $paragraph->getBehaviorSetting($this->getPluginId(), 'fragment')) {
      $summary[] = [
        'label' => 'Link fragment',
        'value' => '#' . $fragment_value,
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
