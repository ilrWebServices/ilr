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
use Drupal\ilr_employee_data\PublicationsFetcher;
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
    protected PublicationsFetcher $publicationsFetcher
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
      $container->get('ilr_employee_data.publications')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $form['netid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('NetID'),
      '#description' => $this->t('(optional) Enter a NetID here to fetch publications from remote services (currently Activity Insight).'),
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
      $publications_data = $this->publicationsFetcher->getPublications($netid);

      $build['remote_publications']['label'] = [
        '#type' => 'inline_template',
        '#template' => '<h2 class="cu-heading">{{ title }}</h2>',
        '#context' => [
          'title' => $this->t('Publications'),
        ],
      ];

      foreach ($publications_data as $pubgroup => $items) {
        $clean_pubgroup = Html::cleanCssIdentifier($pubgroup);

        $build['remote_publications'][$clean_pubgroup] = [
          '#theme' => 'item_list__remote_publications',
          '#title' => $pubgroup,
          '#items' => [],
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
