<?php

namespace Drupal\ilr_employee_data\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;

class DirectoryController extends ControllerBase {

  public function view(Request $request): array {
    $persona_storage = $this->entityTypeManager()->getStorage('persona');
    $position_storage = $this->entityTypeManager()->getStorage('ilr_employee_position');
    $term_storage = $this->entityTypeManager()->getStorage('taxonomy_term');
    $text_filter = $request->query->get('s') ?? '';
    $role_filter = $request->query->get('role') ?? '';
    $dept_filter = $request->query->get('dept') ?? '';

    $build = [];
    // $build['#cache'] = ['max-age' => 0];
    $build['#cache'] = [
      'keys' => ['ilr_employee_directory'],
      'tags' => ['persona_list:ilr_employee', 'taxonomy_term:ilr_employee_role', 'taxonomy_term:organizational_units']
    ];
    $build = [
      '#theme' => 'container__employee_directory',
      '#children' => [
        'filters' => [
          '#theme' => 'container__employee_directory_filters',
          '#children' => [
            'search' => [
              '#type' => 'inline_template',
              '#template' => '<div class="search-filter form-item cu-input-list__item has-float-label"><label for="search" class="cu-label">Search for people</label><input id="search" name="s" value="{{ default }}" class="form-text cu-input cu-input--text is-touched"></div>',
              '#context' => [
                'default' => '',
              ],
            ],
            'role' => [
              '#type' => 'inline_template',
              '#template' => '<select name="role" class="role-filter cu-input"><option value="">All people</option>{% for option in options %}<option {{ option.attr }} value="{{ option.val }}">{{ option.val }}</option>{% endfor %}</select>',
              '#context' => [
                'options' => [],
              ],
            ],
            'department' => [
              '#type' => 'inline_template',
              '#template' => '<select name="dept" class="department-filter cu-input"><option value="">Any department</option>{% for option in options %}<option {{ option.attr }} value="{{ option.val }}">{{ option.val }}</option>{% endfor %}</select>',
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

    if ($text_filter) {
      $build['#children']['filters']['#children']['search']['#context']['default'] = urldecode($text_filter);
    }

    $active_employee_query = \Drupal::database()->select('persona', 'p')
      ->condition('p.type', 'ilr_employee')
      ->condition('p.status', 1);

    $active_employee_role_tids_query = clone $active_employee_query;
    $active_employee_role_tids_query->join('persona__field_employee_role', 'r', 'p.pid = r.entity_id and p.vid = r.revision_id');
    $active_employee_role_tids_query->fields('r', ['field_employee_role_target_id']);
    $active_employee_role_tids_query->distinct();

    $role_ids = $term_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'ilr_employee_role')
      ->condition('tid', $active_employee_role_tids_query, 'IN')
      ->sort('weight')
      ->sort('parent')
      ->execute();

    $roles = $term_storage->loadMultiple($role_ids);

    foreach ($roles as $role) {
      $option = [
        'val' => $role->name->value,
        'attr' => (urldecode($role_filter) === $role->name->value) ? 'selected' : '',
      ];
      $build['#children']['filters']['#children']['role']['#context']['options'][] = $option;
    }

    $active_employee_dept_tids_query = clone $active_employee_query;
    $active_employee_dept_tids_query->join('ilr_employee_position', 'pos', 'p.pid = pos.persona');
    $active_employee_dept_tids_query->fields('pos', ['department']);
    $active_employee_dept_tids_query->distinct();

    $department_ids = $term_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'organizational_units')
      ->condition('tid', $active_employee_dept_tids_query, 'IN')
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
      ->condition('type', 'ilr_employee')
      ->condition('status', 1);

    if ($text_filter) {
      $text_filter_group = $employee_persona_query->orConditionGroup();
      $text_filter_group->condition('field_display_name_override', urldecode($text_filter), 'CONTAINS');
      $text_filter_group->condition('field_first_name', urldecode($text_filter), 'CONTAINS');
      $text_filter_group->condition('field_last_name', urldecode($text_filter), 'CONTAINS');
      $text_filter_group->condition('display_name', urldecode($text_filter), 'CONTAINS');
      $employee_persona_query->condition($text_filter_group);
    }

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

    if (empty($employee_persona_ids)) {
      $build['#children']['message'] = [
        '#markup' => '<p>' . $this->t('No people that match that search. Sorry!') . '</p>',
      ];

      return $build;
    }

    $employee_personas = $persona_storage->loadMultiple($employee_persona_ids);
    $persona_view_builder = $this->entityTypeManager()->getViewBuilder('persona');

    /** @var \Drupal\person\PersonaInterface $employee_persona */
    foreach ($employee_personas as $employee_persona) {
      $this_employee_departments = [];
      $this_employee_titles = [];
      $this_employee_positions = $position_storage->loadByProperties([
        'persona' => $employee_persona->id()
      ]);

      foreach ($this_employee_positions as $this_employee_position) {
        $this_employee_departments[] = $this_employee_position->department->entity->name->value;
        $this_employee_titles[] = $this_employee_position->title->value;
      }

      $search_index = [
        $employee_persona->getDisplayName(),
        $employee_persona->field_first_name->value,
        $employee_persona->field_last_name->value,
        $employee_persona->field_display_name_override->value ?? '',
        implode('  ', $this_employee_departments),
        implode('  ', $this_employee_titles),
      ];

      $search_index = array_map('strtolower', $search_index);

      $directory_view = $persona_view_builder->view($employee_persona, 'directory');
      $directory_view['#attributes']['data-display-name'] = $employee_persona->getDisplayName();
      $directory_view['#attributes']['data-first-name'] = $employee_persona->field_first_name->value;
      $directory_view['#attributes']['data-last-name'] = $employee_persona->field_last_name->value;
      $directory_view['#attributes']['data-departments'] = implode("\t", $this_employee_departments);
      $directory_view['#attributes']['data-role'] = $employee_persona->field_employee_role->entity->name->value;
      $directory_view['#attributes']['data-search-index'] = implode("\t", $search_index);
      $build['#children']['personas']['#children'][$employee_persona->id()] = $directory_view;
    }

    return $build;
  }

}
