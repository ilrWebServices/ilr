<?php

namespace Drupal\ilr_employee_data\Plugin\ExtraField\Display;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\extra_field\Plugin\ExtraFieldDisplayBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\extra_field\Attribute\ExtraFieldDisplay;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[ExtraFieldDisplay(
  id: 'ilr_ecornell_certificates_authored',
  label: new TranslatableMarkup('Certificates authored'),
  bundles: ['persona.ilr_employee'],
  visible: TRUE,
)]
class CertificatesAuthored extends ExtraFieldDisplayBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
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
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function view(ContentEntityInterface $entity) {
    $build = [];
    $netid = $entity->field_netid->value;

    if (empty($netid)) {
      return $build;
    }

    $remote_certificates = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'field_instructor_remote_id' => $netid,
      'status' => 1,
    ]);

    if (empty($remote_certificates)) {
      return $build;
    }

    $build['certificates'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['profile__ecornell-certificates']],
      '#cache' => ['tags' => ['node_list:remote_program']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('eCornell Certificates'),
        '#attributes' => ['class' => ['cu-heading']],
      ],
      'items' => [
        '#theme' => 'item_list__ecornell_certificates',
        '#items' => [],
      ],
    ];

    foreach ($remote_certificates as $remote_program_nid => $remote_program_node) {
      $build['certificates']['items']['#items'][] = $remote_program_node->toLink(NULL, 'canonical', [
        'attributes' => [
          'class' => ['profile__ecornell-certificates-item-link'],
        ],
      ])->toRenderable();
    }

    return $build;
  }

}
