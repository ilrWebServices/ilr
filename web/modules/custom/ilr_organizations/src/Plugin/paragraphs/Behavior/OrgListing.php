<?php

namespace Drupal\ilr_organizations\Plugin\paragraphs\Behavior;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an Organization Listing plugin.
 *
 * @ParagraphsBehavior(
 *   id = "ilr_organization_listing",
 *   label = @Translation("Organization listing"),
 *   description = @Translation("Configure organization listing settings."),
 *   weight = 1
 * )
 */
class OrgListing extends ParagraphsBehaviorBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Creates a new OrgListing behavior.
   *
   * @param array $configuration
   *   The configuration array.
   * @param string $plugin_id
   *   This plugin id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   Entity field manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_field_manager);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    // There is no #parents key in $form, but this may be OK hardcoded.
    $parents = $form['#parents'];
    $parents_input_name = array_shift($parents);
    $parents_input_name .= '[' . implode('][', $parents) . ']';

    // Org types (or groups) are vocabularies with the `is_organization` third
    // party setting.
    $org_types = [];
    $default_org_type_ids = $paragraph->getBehaviorSetting($this->getPluginId(), 'org_types') ?? [];
    $org_term_options = [];
    $org_term_option_descriptions = [];
    $vocab_storage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');

    /** @var \Drupal\taxonomy\VocabularyInterface $vocab */
    foreach ($vocab_storage->loadMultiple() as $vocab) {
      if ($vocab->getThirdPartySetting('ilr_organizations', 'is_organization')) {
        $org_types[$vocab->id()] = $vocab->label();
      }
    }

    if (!empty($default_org_type_ids)) {
      if ($org_terms = $term_storage->loadByProperties(['vid' => $default_org_type_ids])) {
        foreach ($org_terms as $org_tid => $org_term) {
          $org_term_options[$org_tid] = $org_term->label();
          $org_term_option_descriptions[$org_tid] = $org_term->vid->entity->label();
        }

        asort($org_term_options);
      }
    }

    $form['org_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Organization group'),
      '#options' => $org_types,
      '#default_value' => $default_org_type_ids,
      '#ajax' => [
        'callback' => [$this, 'orgTermFieldsUpdate'],
        'event' => 'change',
        'wrapper' => 'edit-org-terms',
      ],
    ];

    if (!empty($org_term_options)) {
      $form['org_terms'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Limit to these selected organizations'),
        '#description' => $this->t('If none are selected, all will be displayed.'),
        '#options' => $org_term_options,
        '#default_value' => array_keys($paragraph->getBehaviorSetting($this->getPluginId(), 'org_terms') ?? []),
        '#multiple' => TRUE,
        '#attributes' => [
          'class' => [
            'organization-selection'
          ],
        ],
        '#prefix' => '<div id="edit-org-terms">',
        '#suffix' => '</div>',
      ];
    }
    else {
      $form['org_terms'] = [
        '#markup' => '<div id="edit-org-terms"></div>',
      ];
    }

    foreach ($org_term_option_descriptions as $tid => $org_term_option_description) {
      $form['org_terms'][$tid]['#attributes']['title'] = $org_term_option_description;
    }

    return $form;
  }

  /**
   * Callback for org term checkbox change event.
   */
  public function orgTermFieldsUpdate(array &$form, FormStateInterface $form_state) {
    // Document what we think is happening here. Why don't we have to change anything in the form? It looks like the buildForm method is called with the new org types selections.
    $trigger = $form_state->getTriggeringElement();
    $behavior_form = NestedArray::getValue($form, array_slice($trigger['#array_parents'], 0, -2));
    return $behavior_form['org_terms'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $filtered_values = $this->filterBehaviorFormSubmitValues($paragraph, $form, $form_state);

    if (!empty($filtered_values['org_terms'])) {
      // Change the orgs array to `$tid => label()`. The values from the
      // checkboxes are initially `$tid => $tid`.
      $org_data = [];

      foreach ($filtered_values['org_terms'] as $org_tid) {
        if ($term = $this->entityTypeManager->getStorage('taxonomy_term')->load($org_tid)) {
          $org_data[$term->id()] = $term->label();
        }
      }

      $filtered_values['org_terms'] = $org_data;
    }

    $paragraph->setBehaviorSettings($this->getPluginId(), $filtered_values);
  }

  /**
   * {@inheritdoc}
   */
  public function validateBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    // Ensure that there is at least one organization selected.
    if (empty(array_filter($form_state->getValue('org_types')))) {
      $form_state->setError($form['org_types'], $this->t('Please choose at least one organization group.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraph, EntityViewDisplayInterface $display, $view_mode) {
    $org_types = $paragraph->getBehaviorSetting($this->getPluginId(), 'org_types') ?? [];
    $selected_orgs = $paragraph->getBehaviorSetting($this->getPluginId(), 'org_terms') ?? [];

    if (empty($org_types) && empty($selected_orgs)) {
      return;
    }

    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $view_builder = $this->entityTypeManager->getViewBuilder('taxonomy_term');
    $cache_tags = [];

    foreach ($org_types as $org_type) {
      $cache_tags[] = 'taxonomy_term_list:' . $org_type;
    }

    if (!empty($selected_orgs)) {
      $tids = array_keys($selected_orgs);
    }
    elseif (!empty($org_types)) {
      $query = $term_storage->getQuery();
      $query->accessCheck(TRUE);
      $query->condition('vid', $org_types, 'IN');
      $query->condition('status', 1);
      $query->sort('weight');
      $tids = $query->execute();
    }

    $organizations = [];

    foreach ($term_storage->loadMultiple($tids) as $term_org) {
      $rendered_entity = $view_builder->view($term_org, 'teaser');
      $rendered_entity['#term'] = $term_org;
      $organizations[] = $rendered_entity;
    }

    $build['org_listing'] = [
      'items' => $organizations,
      '#cache' => [
        'tags' => $cache_tags,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(Paragraph $paragraph) {
    $summary = [];

    if ($selected_org_types = $paragraph->getBehaviorSetting($this->getPluginId(), 'org_types')) {
      $summary[] = [
        'label' => 'Types',
        'value' => implode(', ', $selected_org_types),
      ];
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    return $paragraphs_type->id() === 'organization_listing';
  }

}
