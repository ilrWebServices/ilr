<?php

namespace Drupal\webform_reference_category_select\Hook;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\webform\Utility\WebformElementHelper;

class WebformHooks {

  use StringTranslationTrait;

  #[Hook('field_widget_third_party_settings_form')]
  public function fieldWidgetThirdPartySettingsForm(WidgetInterface $plugin, FieldDefinitionInterface $field_definition, $form_mode, $form, FormStateInterface $form_state) {
    if ($plugin->getPluginId() === 'webform_entity_reference_select') {
      $element = [];

      /** @var \Drupal\webform\WebformEntityStorageInterface $webform_storage */
      $webform_storage = \Drupal::entityTypeManager()->getStorage('webform');
      $element['categories'] = [
        '#type' => 'webform_select_other',
        '#title' => $this->t('Categories'),
        '#options' => $webform_storage->getCategories(NULL, TRUE),
        '#multiple' => TRUE,
        '#select2' => TRUE,
        '#default_value' => $plugin->getThirdPartySetting('webform_reference_category_select', 'categories'),
        '#description' => $this->t('Only Webforms in the selected category (or categories) will be listed in the select menu. If left blank, all categories are listing, unless specific Webforms are selected above.'),
      ];
      WebformElementHelper::process($element['categories']);
      return $element;
    }
  }

  #[Hook('field_widget_settings_summary_alter')]
  public function fieldWidgetSettingsSummaryAlter(&$summary, $context) {
    $plugin = $context['widget'];

    if ($webform_categories = $plugin->getThirdPartySetting('webform_reference_category_select', 'categories')) {
      $summary[] = $this->t('Webform categories: @webform_categories', ['@webform_categories' => implode('; ', $webform_categories)]);
    }
  }

  #[Hook('options_list_alter')]
  public function alterOptionsList(array &$options, array $context) {
    if (isset($context['fieldWidget']) && $filter_categories = $context['fieldWidget']->getThirdPartySetting('webform_reference_category_select', 'categories')) {
      // Reset existing $options, since we'll be overwriting them.
      $options = [];
      $webform_storage = \Drupal::entityTypeManager()->getStorage('webform');

      foreach ($filter_categories as $filter_category) {
        $webforms_for_category = $webform_storage->loadByProperties(['categories.*' => $filter_category]);

        foreach ($webforms_for_category as $webform_for_category) {
          // $options[$filter_category][$webform_for_category->id()] = $webform_for_category->label();
          $options[$webform_for_category->id()] = $webform_for_category->label();
        }
      }
    }
  }

}
