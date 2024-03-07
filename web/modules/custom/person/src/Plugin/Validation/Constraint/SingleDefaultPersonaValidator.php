<?php

namespace Drupal\person\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Validates the SingleDefaultPersona constraint.
 */
class SingleDefaultPersonaValidator extends ConstraintValidator implements ContainerInjectionInterface {

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
    if (!$persona->isDefault()) {
      return;
    }

    // Ensure there is a person for this persona.
    if (!$persona->person->entity) {
      return;
    }

    $person = $persona->person->entity;

    $default_persona_query = $this->personaStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('person', $person->id())
      ->condition('pid', $persona->id(), '!=')
      ->condition('default', 1);

    $default_persona_ids = $default_persona_query->execute();

    if (!empty($default_persona_ids)) {
      $this->context->addViolation($constraint->existing_default, [
        '%person' => $person->label(),
      ]);
    }
  }

}
