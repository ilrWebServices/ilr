<?php

namespace Drupal\person\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Validates the SingleIlrEmployeePersona constraint.
 */
class SingleIlrEmployeePersonaValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * @var Drupal\Core\Entity\Sql\SqlContentEntityStorage
   */
  protected $personaStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->personaStorage = $container->get('entity_type.manager')->getStorage('persona');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function validate($persona, Constraint $constraint) {
    // Ensure that we only validate personas attempting to be set as default.
    if (!$persona->bundle() === 'ilr_employee') {
      return;
    }

    // Ensure there is a person for this persona.
    if (!$persona->person->entity) {
      return;
    }

    $person = $persona->person->entity;

    $employee_persona_query = $this->personaStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('person', $person->id())
      ->condition('type', 'ilr_employee');

    if (!$persona->isNew()) {
      $employee_persona_query->condition('pid', $persona->id(), '!=');
    }

    $employee_persona_ids = $employee_persona_query->execute();

    if (!empty($employee_persona_ids)) {
      $this->context->addViolation($constraint->existing, [
        '%person' => $person->label(),
      ]);
    }
  }

}
