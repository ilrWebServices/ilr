<?php

namespace Drupal\person\Plugin\EntityBrowser\Widget;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\entity_browser\Element\EntityBrowserPagerElement;
use Drupal\entity_browser\WidgetBase;
use Drupal\views\Entity\View as ViewEntity;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
  public function getForm(array &$original_form, FormStateInterface $form_state, array $additional_widget_parameters) {
    $form = parent::getForm($original_form, $form_state, $additional_widget_parameters);
    // steps: person_search, persona_list, new_persona
    $step = $form_state->get('step') ?? 'person_search';

    // $form['debug_message'] = [
    //   '#markup' => $this->t('Current step reported by the element is: @step.', ['@step' => $step]),
    // ];

    $form['search_wrapper'] = [
      '#type' => 'container',
      // '#weight' => 10,
      '#access' => in_array($step, ['person_search', 'persona_list']),
      '#attributes' => ['class' => 'persona-browser__search'],
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

      $tmp = <<<EOT
      <p>Below is a list of people and their personas. Personas represent a person for a specific purpose or context. For example, a person may be displayed in the context of an author of an article or in the context of an employee. Each context may show different information about the person - their photo, bio, or title may be different - but they always represent the same person.</p>

      <details class="persona-browser__more-info">
        <summary>More info</summary>

        <p>Personas represent a particular facet of a person (e.g. in their role as the Director of an Institute, or as an advisor to a project). For that reason, many people at ILR have multiple personas. You can preview a given persona by clicking the preview link below.</p>

        <p>In some cases, new personas were created where existing ones would have worked. You can help us clean up these duplicates by contacting ilrweb@cornell.edu</p>

        <p>Mention persona types, and what they should be used for: Below each persona option checkbox you'll see the persona type, e.g. Author or ILR Employee...</p>

        <p>Also mention the special ilr_employee persona type, and its 'officialness'.</p>
      </details>
      EOT;

      $form['message'] = [
        // '#markup' => $this->t('<p>Select a persona below.</p>'),
        '#markup' => $tmp,
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
        ->sort('person');
      $persona_ids = $query->execute();

      if (empty($persona_ids)) {
        $form['message'] = [
          '#markup' => $this->t('No people found. Create a new one?'),
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
            '#attributes' => ['class' => 'persona-browser__persona-list'],
          ];

          $form['people_items']['person_' . $person->id()]['persona_new'] = [
            '#type' => 'submit',
            '#value' => $this->t('New persona'),
            '#name' => 'person:' . $person->id(),
            '#submit' => [[static::class, 'newPersonaType']],
            '#weight' => 10,
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

        $form['people_items']['person_' . $person->id()]['persona:' . $persona->id()] = [
          '#type' => 'checkbox',
          '#title' => $persona->label(),
          '#description' => $this->t('@type • @preview_link', [
            '@type' => $persona->type->entity->label(),
            '@preview_link' => Link::fromTextAndUrl('details', $persona_url)->toString(),
          ]),
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
        '#value' => $this->t('Create a new person'),
        '#name' => 'person:new',
        '#submit' => [[static::class, 'newPersonaType']],
      ];
    }

    if ($step === 'new_persona_type') {
      $persona_type_storage = $this->entityTypeManager->getStorage('persona_type');

      $tmp = <<<EOT
      Choose a persona type to add. There should be additional text here to help users decide.
      EOT;

      $form['message'] = [
        '#markup' => '<p>' . nl2br($tmp) . '</p>',
        '#weight' => -1,
      ];

      $form['persona_type_wrapper'] = [
        '#type' => 'container',
        '#attributes' => ['class' => 'persona-browser__persona-types'],
      ];

      foreach ($persona_type_storage->loadMultiple() as $persona_type) {
        if (in_array($persona_type->id(), ['ilr_employee', 'principal_investigator', 'visiting_fellow'])) {
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
        'admin_label' => $admin_label,
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
