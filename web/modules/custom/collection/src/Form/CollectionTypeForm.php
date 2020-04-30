<?php

namespace Drupal\collection\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Class CollectionTypeForm.
 */
class CollectionTypeForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $collection_type = $this->entity;
    $allowed_collection_item_types = [];

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $collection_type->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $collection_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\collection\Entity\CollectionType::load',
      ],
      '#disabled' => !$collection_type->isNew(),
    ];

    foreach (\Drupal::service('entity_type.bundle.info')->getBundleInfo('collection_item') as $collection_item_type => $info) {
      $allowed_collection_item_types[$collection_item_type] = $info['label'];
    }

    $form['allowed_collection_item_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed collection item types'),
      '#options' => $allowed_collection_item_types,
      '#default_value' => $collection_type->getAllowedCollectionItemTypes(),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    /** @var \Drupal\collection\Entity\CollectionTypeInterface $entity */
    $values = $form_state->getValues();
    $entity->set('label', $values['label']);
    $entity->set('id', $values['id']);
    $entity->set('allowed_collection_item_types', array_keys(array_filter($values['allowed_collection_item_types'])));
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $collection_type = $this->entity;
    $status = $collection_type->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Collection type.', [
          '%label' => $collection_type->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Collection type.', [
          '%label' => $collection_type->label(),
        ]));
    }
    $form_state->setRedirectUrl($collection_type->toUrl('collection'));
  }

}
