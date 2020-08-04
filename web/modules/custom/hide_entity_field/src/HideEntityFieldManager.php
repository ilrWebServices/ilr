<?php

namespace Drupal\hide_entity_field;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * A service to manage hidden entity fields and settings.
 */
class HideEntityFieldManager {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  private $entityTypeManager;

  /**
   * HideEntityFieldManager constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManager $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public function hideFields(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display) {
    // Check if there are any hidden fields for this entity.
    dump($build);
  }

  /**
   *
   */
  public function hideField(array &$variables) {
    if (!isset($variables['element']['#object'])) {
      return;
    }

    $object = $variables['element']['#object'];

    if (!($object instanceof \Drupal\Core\Entity\ContentEntityInterface)) {
      return;
    }

    // Check if this field is hidden for this entity.
    if (!$this->fieldIsHidden($variables['field_name'], $object)) {
      return;
    }

    // Hide the field items via the #access thing.
    foreach ($variables['items'] as $delta => $item) {
      $variables['items'][$delta]['content']['#access'] = FALSE;
    }
  }

  /**
   * Add hidden field settings to this entity form.
   *
   * @param array $form
   *   Nested array of form elements that comprise the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. The arguments that
   *   \Drupal::formBuilder()->getForm() was originally called with are
   *   available in the array $form_state->getBuildInfo()['args'].
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for this ContentEntityForm.
   */
  public function alterEntityForm(array &$form, FormStateInterface $form_state, EntityInterface $entity) {
    // Ensure that this is an entity add/edit form, not a delete form.
    $build_info = $form_state->getBuildInfo();
    if ($build_info['callback_object'] instanceof ContentEntityDeleteForm) {
      return;
    }

    // TODO Add ThirdPartySetting to entity bundles instead of hardcoding here.
    if ($entity->getEntityTypeId() !== 'node' || $entity->bundle() !== 'post') {
      return;
    }

    $form['hidden_entity_fields_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Hidden fields'),
      '#group' => 'advanced',
      '#weight' => -5,
      '#optional' => FALSE,
      '#open' => TRUE,
    ];

    $options = [
      'field_published_date' => 'Published date',
      'field_representative_image' => 'Representative image',
    ];

    $default_values = [];

    foreach ($options as $option => $label) {
      if ($this->fieldIsHidden($option, $entity)) {
        $default_values[] = $option;
      }
    }

    $form['hidden_entity_fields_settings']['hidden_entity_fields'] = [
      '#type' => 'checkboxes',
      '#group' => 'hidden_entity_fields_settings',
      '#options' => $options,
      '#default_value' => $default_values,
    ];
  }

  protected function fieldIsHidden($field_name, EntityInterface $entity) {
    // TODO Optimize this with a static cache per entity.
    $hidden_entity_field_ids = $this->entityTypeManager->getStorage('hidden_entity_field')->getQuery()
      ->condition('entity__target_type', $entity->getEntityTypeId())
      ->condition('entity__target_id', $entity->id())
      ->condition('field_name', $field_name)
      ->execute();

    return !empty($hidden_entity_field_ids);
  }

}

