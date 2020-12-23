<?php

namespace Drupal\person\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the form for managing persona types.
 */
class PersonaTypeForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $persona_type = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $persona_type->label(),
      '#description' => $this->t("The human-readable name of this Persona type."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $persona_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\person\Entity\PersonaType::load',
      ],
      '#disabled' => !$persona_type->isNew(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $persona_type = $this->entity;
    $status = $persona_type->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Persona type.', [
          '%label' => $persona_type->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Persona type.', [
          '%label' => $persona_type->label(),
        ]));
    }

    $form_state->setRedirectUrl($persona_type->toUrl('collection'));
  }

}
