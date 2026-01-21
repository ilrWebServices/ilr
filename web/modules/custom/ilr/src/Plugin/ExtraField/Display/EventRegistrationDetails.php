<?php

namespace Drupal\ilr\Plugin\ExtraField\Display;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\extra_field\Plugin\ExtraFieldDisplayBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ilr\Entity\EventLandingNode;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Event registration detilas Extra field Display.
 *
 * @ExtraFieldDisplay(
 *   id = "event_registration_details",
 *   label = @Translation("Registration details"),
 *   bundles = {
 *     "node.event_landing_page",
 *   },
 *   visible = true
 * )
 */
class EventRegistrationDetails extends ExtraFieldDisplayBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  public function __construct(
    array $configuration,
    protected string $plugin_id,
    protected mixed $plugin_definition,
    protected ConfigFactoryInterface $config
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function view(ContentEntityInterface $node) {
    if (!$node instanceof EventLandingNode) {
      return [];
    }

    $build['registration_details'] = [
      'registration_form' => !$node->field_registration_form->isEmpty() ? $node->field_registration_form->view([
        'label' => 'hidden',
        'type' => 'webform_entity_reference_entity_view',
      ]) : [],
      'registration_link' => !$node->field_url->isEmpty() ? $node->field_url->view([
        'label' => 'hidden',
        'type' => 'link',
      ]) : [],
      'event_when_where' => [
        '#theme' => 'ilr_event_when_where',
        '#event_date' => $node->event_date->view([
          'label' => 'hidden',
          'type' => 'date_range_without_time',
          'settings' => [
            'timezone_override' => '',
            'one_day' => 'M j, Y',
            'one_month' => 'M j - {j}, Y',
            'several_months' => 'M j - {M} {j}, Y',
            'several_years' => 'M j, Y - {M} {j}, {Y}',
          ],
        ]),
        '#event_time' => $node->event_date->view([
          'label' => 'hidden',
          'type' => 'date_range_without_time',
          'settings' => [
            'timezone_override' => '',
            'one_day' => 'g:i A - {g}:{i} {A} {T}',
            'one_month' => 'g:i A - {g}:{i} {A} {T}',
            'several_months' => 'g:i A - {g}:{i} {A} {T}',
            'several_years' => 'g:i A - {g}:{i} {A} {T}',
          ],
        ]),
        '#location_address' => !$node->location_address->isEmpty() ? $node->location_address->view([
          'label' => 'hidden',
          'type' => 'address_default',
        ]) : '',
        '#location_link' => !$node->location_link->isEmpty() ? $node->location_link->view([
          'label' => 'hidden',
          'type' => 'link',
        ]) : '',
      ]
    ];

    return $build;
  }

}
