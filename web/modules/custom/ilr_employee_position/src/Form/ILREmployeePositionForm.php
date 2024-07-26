<?php

declare(strict_types=1);

namespace Drupal\ilr_employee_position\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the ilr employee position entity edit forms.
 */
final class ILREmployeePositionForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);

    $message_args = ['%label' => $this->entity->toLink()->toString()];
    $logger_args = [
      '%label' => $this->entity->label(),
      'link' => $this->entity->toLink($this->t('View'))->toString(),
    ];

    switch ($result) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('New ILR employee position %label has been created.', $message_args));
        $this->logger('ilr_employee_position')->notice('New ILR employee position %label has been created.', $logger_args);
        break;

      case SAVED_UPDATED:
        $this->messenger()->addStatus($this->t('The ILR employee position %label has been updated.', $message_args));
        $this->logger('ilr_employee_position')->notice('The ILR employee position %label has been updated.', $logger_args);
        break;

      default:
        throw new \LogicException('Could not save the ILR employee position.');
    }

    $form_state->setRedirectUrl($this->entity->toUrl('collection'));

    return $result;
  }

}
