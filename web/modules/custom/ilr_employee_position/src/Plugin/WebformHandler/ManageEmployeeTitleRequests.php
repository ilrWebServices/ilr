<?php

namespace Drupal\ilr_employee_position\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ilr_employee_position\ILREmployeePositionManager;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Employee Title Request webform handler.
 *
 * @WebformHandler(
 *   id = "manage_employee_title_requests",
 *   label = @Translation("Manage Employee Title Requests"),
 *   description = @Translation("Used to handle the custom ILREmployeePosition objects."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 * )
 */
class ManageEmployeeTitleRequests extends WebformHandlerBase implements ContainerFactoryPluginInterface {

  // Safeguards against serialization issues during AJAX submsissions
  use \Drupal\Core\DependencyInjection\DependencySerializationTrait;

  /**
   * The ILR Employee Position Manager Service.
   */
  protected ILREmployeePositionManager $positionManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->positionManager = $container->get('ilr_employee_position.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $webform = $webform_submission->getWebform();

    if ($webform->id() !== 'employee_title_request') {
      return;
    }

    $status = $webform_submission->getelementData('status');
    $data = $webform_submission->getData();
    $pid = (int)$data['pid'];
    $needs_new_title = FALSE;

    if (empty($pid)) {
      return;
    }

    // Check the status of the request, and update their positions if approved.
    if ($status === 'Approved') {
      if ($data['type_of_request'] === 'Adding a title') {
        // Check to see if the title already exists.
        if ($existing_title = $this->positionManager->getEmployeePositionByTitle($pid, $data['title'])) {
          $existing_title = reset($existing_title);
          // Todo: Ensure it's the same department?
          $existing_title->status = TRUE;
          $existing_title->save();
        }
        else {
          $needs_new_title = TRUE;
        }
      }
      elseif ($data['type_of_request'] === 'Updating a title') {
        // Unpublish the existing title.
        if ($title_to_update = $this->positionManager->getEmployeePositionByTitle($pid, $data['title_update_select'])) {
          $title_to_update = reset($title_to_update);
          $is_primary = FALSE;

          if ($title_to_update->status->value === '1') {
            $title_to_update->status = FALSE;
            $title_to_update->save();

            // Ensure that the new title is primary if replacing existing
            // primary title, regardless of value for primary_title.
            if ($title_to_update->primary->value === '1') {
              $is_primary = TRUE;
            }
          }
        }

        // Check to see if the requested title exists.
        if ($existing_title = $this->positionManager->getEmployeePositionByTitle($pid, $data['title'])) {
          $existing_title = reset($existing_title);
          if ($existing_title->status->value !== '1') {
            $existing_title->status = TRUE;
            $existing_title->save();
          }
        } else {
          // Add the title.
          $needs_new_title = TRUE;
        }
      }
      elseif ($data['type_of_request'] === 'Removing a title'){
        if ($title_to_remove = $this->positionManager->getEmployeePositionByTitle($pid, $data['title_update_select'])) {
          $title_to_remove = reset($title_to_remove);
          $title_to_remove->status = FALSE;
          $title_to_remove->save();
        }
      }

      if ($needs_new_title) {
        $new_position = $this->entityTypeManager->getStorage('ilr_employee_position')->create([
          'persona' => $pid,
          'title' => $data['title'],
          'department' => $data['department'],
          'primary' => $is_primary,
          'status' => TRUE,
        ]);

        $new_position->save();
      }
    }
  }

}
