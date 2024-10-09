<?php

namespace Drupal\ilr_employee_data\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

class DirectoryController extends ControllerBase {

  public function view(Request $request): array {
    $persona_storage = $this->entityTypeManager()->getStorage('persona');
    $position_storage = $this->entityTypeManager()->getStorage('ilr_employee_position');
    $term_storage = $this->entityTypeManager()->getStorage('taxonomy_term');
    $role_filter = $request->query->get('role') ?? '';
    $dept_filter = $request->query->get('dept') ?? '';

    $build = [];
    // $build['#cache'] = ['max-age' => 0];
    $build = [
      '#theme' => 'container__employee_directory',
      '#children' => [
        'filters' => [
          '#theme' => 'container__employee_directory_filters',
          '#children' => [
            'role' => [
              '#theme' => 'links__role_filter',
              '#links' => [
                [
                  'url' => Url::fromRoute('ilr_employee_data.directory'),
                  'title' => $this->t('All'),
                ],
              ],
              '#attributes' => ['class' => 'role-filter'],
            ],
            'department' => [
              '#type' => 'inline_template',
              '#template' => '<select name="dept" class="department-filter cu-input"><option value="">- Any department -</option>{% for option in options %}<option {{ option.attr }} value="{{ option.val }}">{{ option.val }}</option>{% endfor %}</select>',
              '#context' => [
                'options' => [],
              ],
            ],
          ],
          '#attributes' => ['class' => 'filters'],
          '#attached' => ['library' => ['union_organizer/form']],
        ],
        'personas' => [
          '#theme' => 'container__employee_directory_items',
          '#children' => [],
        ],
      ],
      '#attributes' => ['class' => ['employee-directory']],
      '#attached' => ['library' => ['ilr_employee_data/directory']],
    ];

    $role_ids = $term_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'ilr_employee_role')
      ->condition('parent.target_id', '0')
      ->sort('weight')
      ->execute();

    $roles = $term_storage->loadMultiple($role_ids);

    foreach ($roles as $role) {
      $role_link_options = [];

      if (urldecode($role_filter) === $role->name->value) {
        $role_link_options['attributes'] = ['class' => 'active'];
      }

      $build['#children']['filters']['#children']['role']['#links'][] = [
        'url' => Url::fromRoute('ilr_employee_data.directory', ['role' => $role->name->value], $role_link_options),
        'title' => $role->name->value,
      ];
    }

    $department_ids = $term_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'organizational_units')
      ->sort('name')
      ->execute();

    $departments = $term_storage->loadMultiple($department_ids);

    foreach ($departments as $department) {
      $option = [
        'val' => $department->name->value,
        'attr' => (urldecode($dept_filter) === $department->name->value) ? 'selected' : '',
      ];
      $build['#children']['filters']['#children']['department']['#context']['options'][] = $option;
    }

    $employee_persona_query = $persona_storage->getQuery()
      ->accessCheck()
      ->sort('field_last_name')
      ->condition('type', 'ilr_employee');

    if ($role_filter) {
      $role_filter_group = $employee_persona_query->orConditionGroup();
      $role_filter_group->condition('field_employee_role.entity.name', urldecode($role_filter));
      $role_filter_group->condition('field_employee_role.entity.parent.entity.name', urldecode($role_filter));
      $employee_persona_query->condition($role_filter_group);
    }

    if ($dept_filter) {
      $dept_position_ids = $position_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('department.entity.name', urldecode($dept_filter))
        ->execute();

      if (!empty($dept_position_ids)) {
        $dept_positions = $position_storage->loadMultiple($dept_position_ids);
        $persona_ids_with_positions = [];

        /** @var \Drupal\ilr_employee_position\Entity\ILREmployeePosition $dept_position */
        foreach ($dept_positions as $dept_position) {
          $persona_ids_with_positions[] = $dept_position->persona->target_id;
        }

        $employee_persona_query->condition('pid', $persona_ids_with_positions, 'IN');
      }
    }

    $employee_persona_ids = $employee_persona_query->execute();
    $employee_personas = $persona_storage->loadMultiple($employee_persona_ids);
    $persona_view_builder = \Drupal::entityTypeManager()->getViewBuilder('persona');

    /** @var \Drupal\person\PersonaInterface $employee_persona */
    foreach ($employee_personas as $employee_persona) {
      $this_employee_deptartments = [];
      $this_employee_titles = [];
      $this_employee_positions = $position_storage->loadByProperties([
        'persona' => $employee_persona->id()
      ]);

      foreach ($this_employee_positions as $this_employee_position) {
        $this_employee_deptartments[] = $this_employee_position->department->entity->name->value;
        $this_employee_titles[] = $this_employee_position->title->value;
      }

      $search_index = [
        $employee_persona->getDisplayName(),
        $employee_persona->field_first_name->value,
        $employee_persona->field_last_name->value,
        implode('  ', $this_employee_deptartments),
        implode('  ', $this_employee_titles),
      ];

      $search_index = array_map('strtolower', $search_index);

      $directory_view = $persona_view_builder->view($employee_persona, 'directory');
      $directory_view['#attributes']['data-display-name'] = $employee_persona->getDisplayName();
      $directory_view['#attributes']['data-first-name'] = $employee_persona->field_first_name->value;
      $directory_view['#attributes']['data-last-name'] = $employee_persona->field_last_name->value;
      $directory_view['#attributes']['data-departments'] = implode("\t", $this_employee_deptartments);
      $directory_view['#attributes']['data-search-index'] = implode("\t", $search_index);
      $build['#children']['personas']['#children'][$employee_persona->id()] = $directory_view;
    }

    return $build;
  }

}
