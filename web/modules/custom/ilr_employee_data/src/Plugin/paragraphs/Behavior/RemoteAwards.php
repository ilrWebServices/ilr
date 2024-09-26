<?php

namespace Drupal\ilr_employee_data\Plugin\paragraphs\Behavior;

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
 * Provides a Remote Awards plugin.
 *
 * @ParagraphsBehavior(
 *   id = "remote_awards",
 *   label = @Translation("Remote awards"),
 *   description = @Translation("Configure remote awards and honors settings."),
 *   weight = 1
 * )
 */
class RemoteAwards extends ParagraphsBehaviorBase {

  /**
   * Creates a new RemoteAwards behavior.
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
    $form['netid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('NetID'),
      '#description' => $this->t('(optional) Enter a NetID here to fetch honors and awards from remote services (currently Activity Insight).'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'netid') ?? '',
    ];

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
  public function view(array &$build, Paragraph $paragraph, EntityViewDisplayInterface $display, $view_mode) {
    $netid = $paragraph->getBehaviorSetting($this->getPluginId(), 'netid');

    if ($netid) {
      $awards_data = $this->remoteDataHelper->getAwards($netid);

      $build['remote_awards'] = [
        '#theme' => 'item_list__remote_awards',
        '#items' => [],
      ];

      foreach ($awards_data as $award) {
        $build['remote_awards']['#items'][] = [
          '#type' => 'component',
          '#component' => 'ilr_employee_data:' . strtolower($award->getType()),
          '#props' => $award->getProperties(),
        ];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(Paragraph $paragraph) {
    $summary = [];
    $remote_awards_netid = $paragraph->getBehaviorSetting($this->getPluginId(), 'netid');

    if ($remote_awards_netid) {
      $summary[] = [
        'label' => 'Remote awards',
        'value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'netid'),
      ];
    }

    if ($paragraph->hasField('field_body') && $paragraph->get('field_body')->isEmpty() === FALSE) {
      $summary[] = [
        'label' => 'Custom awards',
        'value' => $this->t('Yes'),
      ];
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * This behavior is only applicable to `honors_and_awards` paragraphs.
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    return $paragraphs_type->id() === 'honors_and_awards';
  }

}
