<?php

namespace Drupal\ilr_employee_position\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\person\Entity\Persona;

/**
 * Provides a sortable listing of ILR Employee Positions for a Persona entity.
 */
class PersonaIlrEmployeePositionsForm extends FormBase {

  use MessengerTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ilr_employee_position_persona_sort';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?Persona $persona = NULL) {
    $form['table-row'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Title'),
        $this->t('Department'),
        $this->t('Primary'),
        $this->t('Status'),
        $this->t('Weight'),
        $this->t('Operations'),
      ],
      '#empty' => $this->t('There are no ILR employee positions to sort!'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
        ],
      ],
    ];

    // Build the table rows and columns.
    $iep_ids = \Drupal::entityQuery('ilr_employee_position')
      ->accessCheck(FALSE)
      ->condition('status', '1')
      ->condition('persona', $persona->id())
      ->sort('weight')
      ->sort('primary', 'DESC')
      ->sort('title')
      ->execute();

    $position_storage = \Drupal::entityTypeManager()->getStorage('ilr_employee_position');
    $positions = $position_storage->loadMultiple($iep_ids);

    foreach ($positions as $pid => $position) {
      $form['table-row'][$pid]['#attributes']['class'][] = 'draggable';
      $form['table-row'][$pid]['#weight'] = $position->weight->value;

      $form['table-row'][$pid]['title'] = [
        '#markup' => $position->get('title')->value,
      ];

      $form['table-row'][$pid]['department'] = [
        '#markup' => $position->get('department')->entity->label(),
      ];

      $form['table-row'][$pid]['primary'] = [
        '#markup' => $position->get('primary')->value ? $this->t('Yes') : $this->t('No')
      ];

      $form['table-row'][$pid]['status'] = [
        '#markup' => $position->get('status')->value ? $this->t('Enabled') : $this->t('Disabled')
      ];

      $form['table-row'][$pid]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', [
          '@title' => $position->label(),
        ]),
        '#title_display' => 'invisible',
        '#default_value' => $position->weight->value,
        '#attributes' => [
          'class' => [
            'table-sort-weight',
          ],
        ],
      ];
    }

    $operations = [];
    if ($position->access('update') && $position->hasLinkTemplate('edit-form')) {
      // $edit_url = $this->ensureDestination($position->toUrl('edit-form'));
      $edit_url = $position->toUrl('edit-form');
      if (!empty($position->label())) {
        $label = $this->t('Edit @entity_label', ['@entity_label' => $position->label()]);
      }
      else {
        $label = $this->t('Edit @entity_bundle @entity_id', ['@entity_bundle' => $position->bundle(), '@entity_id' => $position->id()]);
      }
      $attributes = $edit_url->getOption('attributes') ?: [];
      $attributes += ['aria-label' => $label];
      $edit_url->setOption('attributes', $attributes);

      $operations['edit'] = [
        'title' => $this->t('Edit'),
        'weight' => 10,
        'url' => $edit_url,
      ];
    }


    $form['table-row'][$pid]['operations'] = [
      '#type' => 'operations',
      '#links' => $operations,
    ];

    // $links['view_diff'] = [
    //   'title' => $this->t('View differences'),
    //   'url' => Url::fromRoute($route_name, $route_options),
    //   'attributes' => [
    //     'class' => ['use-ajax'],
    //     'data-dialog-type' => 'modal',
    //     'data-dialog-options' => json_encode([
    //       'width' => 700,
    //     ]),
    //   ],
    // ];

    // $form[$collection][$config_change_type]['list']['#rows'][] = [
    //   'name' => $config_name,
    //   'operations' => [
    //     'data' => [
    //       '#type' => 'operations',
    //       '#links' => $links,
    //     ],
    //   ],
    // ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save order'),
    ];
    // $form['actions']['cancel'] = [
    //   '#type' => 'submit',
    //   '#value' => 'Cancel',
    //   '#attributes' => [
    //     'title' => $this->t('Return to Hunt Management settings'),
    //   ],
    //   '#submit' => [
    //     '::cancel',
    //   ],
    //   '#limit_validation_errors' => [],
    // ];

    return $form;
  }

  /**
   * Form submission handler for the 'Return to' action.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  // public function cancel(array &$form, FormStateInterface $form_state) {
  //   $form_state->setRedirect('huntmgmt_core.admin_config_huntmgmt');
  // }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $submission = $form_state->getValue('table-row');
    $position_storage = \Drupal::entityTypeManager()->getStorage('ilr_employee_position');

    foreach ($submission as $pid => $item) {
      $position = $position_storage->load($pid);
      $position->set('weight', $item['weight']);
      $position->save();
    }

    $this->messenger()->addMessage($this->t('Display sort set!'));
  }

  /**
   * Route title callback.
   */
  public function getTitle(Persona $persona) {
    return $this->t('ILR Employee Positions for %title', [
      '%title' => $persona->label(),
    ]);
  }

}
