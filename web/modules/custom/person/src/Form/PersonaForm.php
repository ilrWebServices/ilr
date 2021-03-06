<?php

namespace Drupal\person\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Persona add/edit forms.
 *
 * @ingroup person
 */
class PersonaForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $persona = $this->entity;

    if (isset($form['person']) && isset($persona->person->entity)) {
      $form['person']['widget']['#disabled'] = TRUE;
      $form['person']['widget']['info'] = [
        '#type' => 'link',
        '#title' => $this->t('Edit') . ' ' . $persona->person->entity->label(),
        '#url' => $persona->person->entity->toUrl('edit-form'),
      ];

      $form['inherited'] = [
        '#type' => 'details',
        '#title' => $this->t('Inherited Fields'),
        '#description' => isset($persona->person->entity) ? $this->t('The values of these fields are inherited from @link. If modified here, they will override the original values.', [
          '@link' => $persona->person->entity->toLink(NULL, 'edit-form')->toString(),
        ]) : '',
        '#collapsible' => TRUE,
        '#open' => FALSE,
        '#weight' => -50,
      ];

      foreach ($persona->type->entity->getInheritedFieldNames() as $field_name) {
        if (isset($form[$field_name]) && (!$persona->fieldIsOverridden($field_name) || $persona->$field_name->isEmpty())) {
          $form['inherited'][$field_name] = $form[$field_name];
          $form['inherited'][$field_name]['widget'][0]['value']['#placeholder'] = isset($persona->person->entity) ? $persona->person->entity->$field_name->value : '';
          // If the render element is at the root, we need to hide it. For all
          // other field widgets, we need to unset the field.
          if (empty($form[$field_name]['widget'][0])) {
            $form[$field_name]['#access'] = FALSE;
          }
          else {
            unset($form[$field_name]);
          }
        }
      }
    }

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

    $form_state->setRedirect('entity.persona.collection');
  }

}
