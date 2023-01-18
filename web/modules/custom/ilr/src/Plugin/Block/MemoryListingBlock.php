<?php

namespace Drupal\ilr\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a memory listing block.
 *
 * @Block(
 *   id = "ilr_memory_listing",
 *   admin_label = @Translation("Memory Listing"),
 *   category = @Translation("ILR")
 * )
 */
class MemoryListingBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new MemoryListingBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return [
      'webform_submission_list:memories',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build['items'] = [];
    $submission_storage = $this->entityTypeManager->getStorage('webform_submission');

    $query = $submission_storage->getQuery();
    $query->accessCheck(FALSE);
    $query->condition('webform_id', 'memories');
    $query->condition('locked', 1);
    $query->sort('completed', 'DESC');
    $result = $query->execute();
    $submissions = $result ? $submission_storage->loadMultiple($result) : [];

    if (empty($submissions)) {
      return [];
    }

    $view_builder = $this->entityTypeManager->getViewBuilder('webform_submission');

    foreach ($submissions as $submission) {
      $build['items'][] = $view_builder->view($submission);
    }

    return $build;
  }

}
