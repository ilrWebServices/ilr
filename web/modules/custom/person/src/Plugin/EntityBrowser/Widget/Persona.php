<?php

namespace Drupal\person\Plugin\EntityBrowser\Widget;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\entity_browser\WidgetBase;

/**
 * Uses a view to provide entity listing in a browser's widget.
 *
 * @EntityBrowserWidget(
 *   id = "persona_entity_browser",
 *   label = @Translation("Persona"),
 *   description = @Translation("Custom person/persona browser widget."),
 *   auto_select = TRUE
 * )
 */
class Persona extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array_merge(parent::defaultConfiguration(), [
      // This should get populated with the field widget target_bundles settings
      // via self::handleWidgetContext().
      'target_bundles' => [],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array &$original_form, FormStateInterface $form_state, array $additional_widget_parameters) {
    $form = parent::getForm($original_form, $form_state, $additional_widget_parameters);
    $allowed_bundles = $this->getConfiguration()['settings']['target_bundles'] ?? [];

    // steps: person_search, persona_list, new_persona
    $step = $form_state->get('step') ?? 'person_search';

    $form['search_wrapper'] = [
      '#type' => 'container',
      '#access' => in_array($step, ['person_search', 'persona_list']),
      '#attributes' => ['class' => ['persona-browser__search']],
    ];

    $form['search_wrapper']['search_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#size' => 20,
      '#required' => TRUE,
    ];

    $form['search_wrapper']['search_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#submit' => [[static::class, 'submitPersonSearch']],
    ];

    if ($step === 'persona_list') {
      $name = $form_state->getValue('search_name');

      $persona_help = <<<EOT
      <p>Below is a list of people that match your search. Please check a persona and click the <em>Select</em> button to add them to the listing. Alternatively, add a new persona appropriate for this content.</p>

      <details class="persona-browser__more-info">
        <summary>More about personas</summary>

        <p>Personas represent a person for a specific purpose or context. For example, a person may be displayed in the context of an author of an article or in the context of an employee. People can have multiple personas, and each one may show unique information about the person - their photo, bio, or title may be different - but they always represent the same person.</p>

        <p>You can view more information about a given persona by clicking the 'details' link.</p>

        <p>Below each persona checkbox you'll see the persona type, e.g. Author or ILR Employee. Persona types are used in different situations. For example, only Authors and Experts can be added to Posts and Media Mentions.</p>

        <p>The 'ILR Employee' persona type is an official persona that is automatically kept up-to-date. These personas represent ILR faculty and staff.</p>
      </details>
      EOT;

      $form['message'] = [
        '#markup' => $persona_help,
        '#weight' => 5,
      ];

      $form['people_items'] = [
        '#type' => 'container',
        '#weight' => 10,
      ];

      $persona_storage = $this->entityTypeManager->getStorage('persona');
      $query = $persona_storage->getQuery()
        ->accessCheck(TRUE)
        ->range(0, 100)
        ->condition('status', 1)
        ->condition('person.entity.display_name', $name, 'CONTAINS')
        // ->condition('type', $allowed_bundles, 'IN')
        ->sort('person');
      $persona_ids = $query->execute();

      if (empty($persona_ids)) {
        $form['message'] = [
          '#markup' => $this->t('No people found.'),
        ];
      }

      $personas = $persona_storage->loadMultiple($persona_ids);

      /** @var \Drupal\person\PersonaInterface */
      foreach ($personas as $persona) {
        $person = $persona->person->entity;

        if (!isset($form['people_items']['person_' . $person->id()])) {
          $form['people_items']['person_' . $person->id()] = [
            '#type' => 'fieldset',
            '#title' => $person->label(),
            '#attributes' => ['class' => ['persona-browser__persona-list']],
          ];

          $form['people_items']['person_' . $person->id()]['persona_new'] = [
            '#type' => 'submit',
            '#value' => $this->t('New persona'),
            '#name' => 'person:' . $person->id(),
            '#submit' => [[static::class, 'newPersonaType']],
            '#weight' => 10,
            '#attributes' => ['class' => ['link']],
          ];

          if (!$person->field_photo->isEmpty()) {
            $file = $person->field_photo->entity->field_media_image->entity;
            $image_uri = $file->getFileUri();

            $form['people_items']['person_' . $person->id()]['photo'] = [
              '#theme' => 'image_style',
              '#style_name' => 'thumbnail',
              '#uri' => $image_uri,
              '#width' => 100,
              '#height' => 100,
              // '#alt' => $alt,
              '#attributes' => [
                'loading' => 'lazy',
                'title' => $this->t('Person id @pid', ['@pid' => $person->id()]),
              ],
            ];
          }
        }

        $persona_url = $persona->toUrl();
        $persona_url->setOption('attributes', [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode([
            'width' => '90%',
            'classes' => ['ui-dialog' => 'cu-modal'],
          ]),
        ]);

        $context = [
          $persona->type->entity->label(),
          Link::fromTextAndUrl('details', $persona_url)->toString(),
        ];

        if ($persona->hasField('note') && !$persona->get('note')->isEmpty()) {
          $context[] = $persona->note->value;
        }

        $form['people_items']['person_' . $person->id()]['persona:' . $persona->id()] = [
          '#type' => 'checkbox',
          '#title' => $persona->label(),
          '#description' => implode(' • ', $context),
          '#access' => in_array($persona->bundle(), $allowed_bundles),
        ];
      }

      $form['new_persona_wrapper'] = [
        '#type' => 'fieldset',
        '#weight' => 10,
      ];

      $form['new_persona_wrapper']['message'] = [
        '#markup' => $this->t("Don't see the person you're looking for?"),
      ];

      $form['new_persona_wrapper']['persona_new'] = [
        '#type' => 'submit',
        '#value' => $this->t('Add a person now'),
        '#name' => 'person:new',
        '#submit' => [[static::class, 'newPersonaType']],
        '#attributes' => ['class' => ['link']],
      ];
    }

    // This is almost working, but something is missing when we don't call
    // newPersonaType() or newPerson(). The intent is to skip the persona type
    // selection when there is only one option.
    // if ($step === 'new_persona_type' && count($allowed_bundles) === 1) {
    //   $step = 'new_persona';
    //   $form_state->set('persona_type', array_values($allowed_bundles)[0]);
    // }

    if ($step === 'new_persona_type') {
      $persona_type_storage = $this->entityTypeManager->getStorage('persona_type');

      $new_persona_help = <<<EOT
      Choose a persona type.
      EOT;

      $form['message'] = [
        '#markup' => '<p>' . nl2br($new_persona_help) . '</p>',
        '#weight' => -1,
      ];

      $form['persona_type_wrapper'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['persona-browser__persona-types']],
      ];

      foreach ($persona_type_storage->loadMultiple($allowed_bundles) as $persona_type) {
        // Prevent the creation of some persona types.
        if (in_array($persona_type->id(), ['ilr_employee', 'visiting_fellow'])) {
          continue;
        }

        $data = [
          'person' => $form_state->get('person'),
          'persona_type' => $persona_type->id(),
        ];

        $form['persona_type_wrapper']['persona_type:' . $persona_type->id()] = [
          '#type' => 'submit',
          '#value' => $this->t('Create @type', ['@type' => $persona_type->label()]),
          '#submit' => [[static::class, 'newPersona']],
          '#attributes' => $data,
        ];
      }
    }

    if ($step === 'new_persona') {
      $persona_type = $form_state->get('persona_type');
      $admin_label = '';

      if ($person_id = $form_state->get('person')) {
        $person_entity = $this->entityTypeManager->getStorage('person')->load($person_id);
        $admin_label = $person_entity ? $person_entity->display_name->value . ' - ' : '';
      }

      $persona_new = $this->entityTypeManager->getStorage('persona')->create([
        'type' => $persona_type,
        'person' => $person_id,
        // 'admin_label' => $admin_label,
      ]);

      // Pretend to be IEFs submit button. See
      // \Drupal\entity_browser_entity_form\Plugin\EntityBrowser\Widget\EntityForm
      // and
      // \Drupal\collection\CollectionContentEntityFormAlter::addNewCollectionItem()
      $form['#submit'] = [['Drupal\inline_entity_form\ElementSubmit', 'trigger']];
      $form['actions']['submit']['#ief_submit_trigger'] = TRUE;
      $form['actions']['submit']['#ief_submit_trigger_all'] = TRUE;

      $form['inline_entity_form'] = [
        '#type' => 'inline_entity_form',
        '#op' => 'add',
        '#entity_type' => 'persona',
        '#bundle' => $persona_new->bundle(),
        '#default_value' => $persona_new,
        '#form_mode' => 'mini',
      ];
    }

    return $form;
  }

  public static function submitPersonSearch(array &$form, FormStateInterface $form_state) {
    $form_state->set('step', 'persona_list');
    $form_state->setRebuild();
  }

  public static function newPersonaType(array &$form, FormStateInterface $form_state) {
    $form_state->set('step', 'new_persona_type');

    $trigger = $form_state->getTriggeringElement();

    if (strpos($trigger['#name'], 'person:') !== FALSE) {
      $form_state->set('person', substr($trigger['#name'], 7));
    }

    $form_state->setRebuild();
  }

  public static function newPersona(array &$form, FormStateInterface $form_state) {
    $form_state->set('step', 'new_persona');
    $trigger = $form_state->getTriggeringElement();
    $form_state->set('person', $trigger['#attributes']['person'] ?? 'new');
    $form_state->set('persona_type', $trigger['#attributes']['persona_type'] ?? 'staff');
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareEntities(array $form, FormStateInterface $form_state) {
    $step = $form_state->get('step');

    if ($step === 'new_persona') {
      return [$form[$form['#browser_parts']['widget']]['inline_entity_form']['#entity']];
    }

    $persona_ids = [];

    foreach ($form_state->getValues() as $key => $value) {
      if (strpos($key, 'persona:') === FALSE || $value !== 1) {
        continue;
      }

      $persona_ids[] = str_replace('persona:', '', $key);
    }

    if (empty($persona_ids)) {
      return [];
    }

    return $this->entityTypeManager->getStorage('persona')->loadMultiple($persona_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array &$element, array &$form, FormStateInterface $form_state) {
    $entities = $this->prepareEntities($form, $form_state);

    if (!empty($form_state->getTriggeringElement()['#eb_widget_main_submit'])) {
      array_walk(
        $entities,
        function (EntityInterface $entity) {
          $entity->save();
        }
      );
    }

    $this->selectEntities($entities, $form_state);
  }

}
