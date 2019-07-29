<?php

namespace Drupal\collection\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class CollectionItemTypeForm.
 */
class CollectionItemTypeForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $collection_item_type = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $collection_item_type->label(),
      '#description' => $this->t("Label for the Collection item type."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $collection_item_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\collection\Entity\CollectionItemType::load',
      ],
      '#disabled' => !$collection_item_type->isNew(),
    ];

    /* You will need additional form elements for your custom properties. */

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $collection_item_type = $this->entity;
    $status = $collection_item_type->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Collection item type.', [
          '%label' => $collection_item_type->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Collection item type.', [
          '%label' => $collection_item_type->label(),
        ]));
    }
    $form_state->setRedirectUrl($collection_item_type->toUrl('collection'));
  }

}
