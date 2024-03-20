<?php

namespace Drupal\ilr_organizations\Plugin\paragraphs\Behavior;

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

    // Org types are vocabularies with the `is_organization` third party setting.
    $org_types = [];
    $vocab_storage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');

    foreach ($vocab_storage->loadMultiple() as $vocab) {
      if ($vocab->getThirdPartySetting('ilr_organizations', 'is_organization')) {
        $org_types[$vocab->id()] = $vocab->label();
      }
    }

    $form['org_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Organization types'),
      '#options' => $org_types,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'org_types') ?? [],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    // Ensure that there is at least one project type selected.
    if (empty(array_filter($form_state->getValue('org_types')))) {
      $form_state->setError($form['org_types'], $this->t('Please choose at least one organization type to display.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    $paragraph = $variables['paragraph'];
    $org_types = $paragraph->getBehaviorSetting($this->getPluginId(), 'org_types') ?? [];

    if (empty($org_types)) {
      return;
    }

    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $view_builder = $this->entityTypeManager->getViewBuilder('taxonomy_term');
    $cache_tags = [];

    foreach ($org_types as $org_type) {
      $cache_tags[] = 'taxonomy_term_list:' . $org_type;
    }

    $query = $term_storage->getQuery();
    $query->accessCheck(TRUE);
    $query->condition('vid', $org_types, 'IN');
    $query->condition('status', 1);
    $query->sort('weight');
    $result = $query->execute();
    $organizations = [];

    foreach ($term_storage->loadMultiple($result) as $term_org) {
      $rendered_entity = $view_builder->view($term_org, 'teaser');
      $rendered_entity['#term'] = $term_org;
      $organizations[] = $rendered_entity;
    }

    $variables['content']['org_listing'] = [
      'items' => $organizations,
      '#cache' => [
        'tags' => $cache_tags,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraphs_entity, EntityViewDisplayInterface $display, $view_mode) {}

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
