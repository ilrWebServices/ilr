<?php

namespace Drupal\ilr_employee_position\Plugin\ExtraField\Display;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Canonical home field Display.
 *
 * @ExtraFieldDisplay(
 *   id = "ilr_employee_positions",
 *   label = @Translation("Employee positions"),
 *   bundles = {
 *     "persona.ilr_employee",
 *   },
 *   visible = true
 * )
 */
class IlrEmployeePositions extends ExtraFieldDisplayBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected $configuration,
    protected $pluginId,
    protected $pluginDefinition,
    protected EntityTypeManagerInterface $entityTypeManager
  ) {}

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
  public function view(ContentEntityInterface $persona) {
    $build = [];

    $position_ids = $this->entityTypeManager->getStorage('ilr_employee_position')->getQuery()
      ->accessCheck(FALSE)
      ->condition('persona', $persona->id())
      ->condition('status', 1)
      ->sort('primary', 'DESC')
      ->execute();

    if (!$position_ids) {
      return $build;
    }

    $positions = $this->entityTypeManager->getStorage('ilr_employee_position')->loadMultiple($position_ids);
    $view_builder = $this->entityTypeManager->getViewBuilder('ilr_employee_position');
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => 'profile-positions'],
    ];

    /** @var \Drupal\ilr_employee_position\ILREmployeePositionInterface $position */
    foreach ($positions as $position) {
      $build[] = $view_builder->view($position);
    }

    return $build;
  }

}
