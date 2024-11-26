<?php

namespace Drupal\person\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for Persona add/edit forms.
 *
 * @ingroup person
 */
class PersonaForm extends ContentEntityForm {

  /**
   * Constructs a PersonaForm object.
   */
  public function __construct(
    EntityRepositoryInterface $entityRepository,
    EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    TimeInterface $time,
    protected DateFormatterInterface $dateFormatter,
    protected AccountProxyInterface $account
  ) {
    parent::__construct($entityRepository, $entityTypeBundleInfo, $time);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('date.formatter'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    /** @var \Drupal\person\Entity\Persona $persona */
    $persona = $this->entity;

    if (isset($form['person']) && isset($persona->person->entity)) {
      $form['person']['widget']['#disabled'] = TRUE;
      $form['person']['widget']['info'] = [
        '#type' => 'link',
        '#title' => $this->t('Edit') . ' ' . $persona->person->entity->label(),
        '#url' => $persona->person->entity->toUrl('edit-form'),
        '#access' => $this->account->hasPermission('administer persons'),
      ];

      $form['inherited'] = [
        '#type' => 'details',
        '#title' => $this->t('Inherited Fields'),
        '#description' => isset($persona->person->entity) ? $this->t('The values of these fields are inherited from @link. If modified here, they will used instead of the original values.', [
          '@link' => $persona->person->entity->toLink(NULL, 'edit-form')->toString(),
        ]) : '',
        '#collapsible' => TRUE,
        '#open' => FALSE,
        '#weight' => -50,
      ];

      foreach ($persona->type->entity->getInheritedFieldNames() as $field_name) {
        if (isset($form[$field_name]) && (!$persona->fieldIsOverridden($field_name) || $persona->$field_name->isEmpty())) {
          $form[$field_name]['#group'] = 'inherited';
        }
      }
    }

    $form['revision']['#default_value'] = TRUE;

    // See person_theme().
    $form['#theme'] = ['persona_edit_form'];

    // Advanced is created in the parent class.
    $form['advanced']['#type'] = 'container';
    $form['advanced']['#attributes']['class'][] = 'entity-meta';
    $form['revision_information']['#type'] = 'container';

    // Similar to NodeForm.
    $form['meta'] = [
      '#type' => 'container',
      '#group' => 'advanced',
      '#weight' => -10,
      '#attributes' => ['class' => ['entity-meta__header']],
      '#tree' => TRUE,
    ];
    $form['meta']['changed'] = [
      '#type' => 'item',
      '#title' => $this->t('Last saved'),
      '#markup' => !$persona->isNew() ? $this->dateFormatter->format($persona->getChangedTime(), 'short') : $this->t('Not saved yet'),
      '#wrapper_attributes' => ['class' => ['entity-meta__last-saved']],
      '#weight' => 100,
    ];

    // `person` and `admin_label` are base fields, while revision_info was added
    // in the parent class.
    $form['person']['#group'] = 'meta';
    $form['admin_label']['#group'] = 'meta';
    $form['revision_information']['#group'] = 'meta';

    // The `note` field is added automatically, but can be removed.
    if (isset($form['note'])) {
      $form['note']['#group'] = 'meta';
    }

    // Place the status/published field at the bottom of the form.
    $form['status']['#group'] = 'footer';

    // Include CSS library for layout.
    $form['#attached']['library'][] = 'person/persona-form';

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

    $form_state->setRedirect('entity.persona.canonical', ['persona' => $this->entity->id()]);
  }

}
