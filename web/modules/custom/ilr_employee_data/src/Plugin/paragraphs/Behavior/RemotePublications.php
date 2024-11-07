<?php

namespace Drupal\ilr_employee_data\Plugin\paragraphs\Behavior;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\ilr_employee_data\RemoteDataHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Remote Publications plugin.
 *
 * @ParagraphsBehavior(
 *   id = "remote_publications",
 *   label = @Translation("Remote publications"),
 *   description = @Translation("Configure remote publications settings."),
 *   weight = 1
 * )
 */
class RemotePublications extends ParagraphsBehaviorBase {

  /**
   * Creates a new RemotePublications behavior.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityFieldManagerInterface $entity_field_manager,
    protected RemoteDataHelper $remoteDataHelper
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_field_manager);
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
      $container->get('ilr_employee_data.remote_data_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $netid = $paragraph->getBehaviorSetting($this->getPluginId(), 'netid') ?? '';
    $publication_group_options = $paragraph->getBehaviorSetting($this->getPluginId(), 'publication_group_options') ?? [];
    $publications_types_indexed_options = array_combine($publication_group_options, $publication_group_options);

    $form['netid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('NetID'),
      '#description' => $this->t('(optional) Enter a NetID here to fetch publications from remote services (currently Activity Insight).'),
      '#default_value' => $netid,
    ];

    if ($netid) {
      $publications_types = array_keys($this->remoteDataHelper->getPublications($netid, FALSE));
      $publications_types_all_options = array_combine($publications_types, $publications_types);

      $form['publication_group_options'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Publication types'),
        '#options' => $publications_types_indexed_options + $publications_types_all_options,
        '#description' => $this->t('Select the publication types to include. You may also sort them in the order they should appear.'),
        '#default_value' => empty($publication_group_options) ? $publications_types : $publication_group_options,
        '#attached' => [
          'library' => ['ilr_employee_data/remote-pubs-enhancements']
        ],
        '#attributes' => [
          'class' => ['publication-group-options'],
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    // @TODO Validate netid.
  }

  /**
   * {@inheritdoc}
   */
  public function submitBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $filtered_values = $this->filterBehaviorFormSubmitValues($paragraph, $form, $form_state);

    if (!empty($filtered_values['publication_group_options'])) {
      // Change the keys to an index.
      $filtered_values['publication_group_options'] = array_values($filtered_values['publication_group_options']);
    }

    $paragraph->setBehaviorSettings($this->getPluginId(), $filtered_values);
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraph, EntityViewDisplayInterface $display, $view_mode) {
    $netid = $paragraph->getBehaviorSetting($this->getPluginId(), 'netid');
    $publication_group_options = $paragraph->getBehaviorSetting($this->getPluginId(), 'publication_group_options') ?? [];

    if ($netid) {
      $publications_data = $this->remoteDataHelper->getPublications($netid, FALSE);

      if (empty($publications_data)) {
        // If the body field is also empty, clear out the build because we don't
        // want to only display the heading.
        if ($paragraph->field_body->isEmpty()) {
          $build = [];
        }

        return;
      }

      foreach ($publications_data as $pubgroup => $items) {
        $clean_pubgroup = Html::cleanCssIdentifier($pubgroup);

        if (!empty($publication_group_options) && !in_array($pubgroup, $publication_group_options)) {
          continue;
        }

        $build['remote_publications'][$clean_pubgroup] = [
          '#theme' => 'item_list__remote_publications',
          '#title' => $pubgroup,
          '#items' => [],
          '#weight' => array_search($pubgroup, $publication_group_options),
        ];

        /** @var BaseType $item */
        foreach ($items as $item) {
          $build['remote_publications'][$clean_pubgroup]['#items'][] = [
            '#type' => 'component',
            '#component' => 'ilr_employee_data:' . strtolower($item->getType()),
            '#props' => $item->getProperties(),
          ];
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(Paragraph $paragraph) {
    $summary = [];
    $remote_publications_netid = $paragraph->getBehaviorSetting($this->getPluginId(), 'netid');

    if ($remote_publications_netid) {
      $summary[] = [
        'label' => 'Remote publications',
        'value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'netid'),
      ];
    }

    if ($paragraph->hasField('field_body') && $paragraph->get('field_body')->isEmpty() === FALSE) {
      $summary[] = [
        'label' => 'Custom publications',
        'value' => $this->t('Yes'),
      ];
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * This behavior is only applicable to `publications` paragraphs.
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    return $paragraphs_type->id() === 'publications';
  }

}
