<?php

namespace Drupal\ilr_employee_position\Plugin\ExtraField\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\extra_field\Plugin\ExtraFieldFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Example Extra field form display.
 *
 * @ExtraFieldForm(
 *   id = "ilr_employee_positions_description",
 *   label = @Translation("Employee positions description"),
 *   bundles = {
 *     "persona.ilr_employee",
 *   },
 *   visible = true
 * )
 */
class IlrEmployeePositionsDescription extends ExtraFieldFormBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager
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
  public function formElement(array &$form, FormStateInterface $form_state) {
    $persona = $form_state->getFormObject()->getEntity();
    $element = [
      '#type' => 'fieldset',
      '#title' => $this->t('Positions (title plus department)'),
      '#description' => $this->t('@edit_link these employee positions.', [
        '@edit_link' => Link::createFromRoute('Reorder or edit', 'ilr_employee_position.persona.ilr_employee_positions', [
          'persona' => $persona->id(),
        ])->toString(),
      ]),
      '#attributes' => ['class' => 'profile-positions'],
    ];

    $position_ids = $this->entityTypeManager->getStorage('ilr_employee_position')->getQuery()
      ->accessCheck(FALSE)
      ->condition('persona', $persona->id())
      ->condition('status', 1)
      ->sort('primary', 'DESC')
      ->execute();

    if (!$position_ids) {
      return $element;
    }

    $positions = $this->entityTypeManager->getStorage('ilr_employee_position')->loadMultiple($position_ids);
    $view_builder = $this->entityTypeManager->getViewBuilder('ilr_employee_position');
    $element['profile_positions'] = [
      '#theme' => 'item_list__profile_positions',
      '#items' => [],
      '#attributes' => ['class' => 'profile-positions'],
    ];

    // /** @var \Drupal\ilr_employee_position\ILREmployeePositionInterface $position */
    foreach ($positions as $position) {
      $element['profile_positions']['#items'][] = $view_builder->view($position);
    }

    return $element;
  }

}
