<?php

namespace Drupal\person\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Person add/edit forms.
 *
 * @ingroup person
 */
class PersonForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['revision']['#default_value'] = TRUE;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created %label.', [
          '%label' => $this->entity->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved %label.', [
          '%label' => $this->entity->label(),
        ]));
    }

    $form_state->setRedirect('entity.persona.add_page', options: [
      'query' => ['person' => $this->entity->id()]
    ]);
  }

}
