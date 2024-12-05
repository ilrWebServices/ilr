<?php

namespace Drupal\person\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class PersonCollectionSearchForm extends FormBase {

  public function getFormId() {
    return 'person_search_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $request = \Drupal::request();

    $form['filter'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['form--inline', 'clearfix'],
      ],
    ];

    $form['filter']['name'] = [
      '#type' => 'textfield',
      '#title' => 'Name',
      '#default_value' => $request->get('name') ?? '',
    ];

    $form['actions']['wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-item']],
    ];

    $form['actions']['wrapper']['submit'] = [
      '#type' => 'submit',
      '#value' => 'Filter',
    ];

    if ($request->getQueryString()) {
      $form['actions']['wrapper']['reset'] = [
        '#type' => 'submit',
        '#value' => 'Reset',
        '#submit' => ['::resetForm'],
      ];
    }

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $query = [];
    $name = $form_state->getValue('name') ?? 0;

    if ($name) {
      $query['name'] = $name;
    }

    $form_state->setRedirect('entity.person.collection', $query);
  }

  public function resetForm(array $form, FormStateInterface &$form_state) {
    $form_state->setRedirect('entity.person.collection');
  }

}
