<?php

namespace Drupal\person\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Validates the UniquePersonaAdminLabelValidator constraint.
 */
class UniquePersonaAdminLabelValidator extends ConstraintValidator implements ContainerInjectionInterface {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager
  ){}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $persona, Constraint $constraint) {
    $query = $this->entityTypeManager->getStorage('persona')->getQuery();
    $query->accessCheck(FALSE);
    $query->condition('admin_label', $persona->admin_label->value);

    // Don't include self when updating.
    if (!$persona->isNew() && $pid = $persona->id()) {
      $query->condition('pid', $pid, '!=');
    }

    if ($result = $query->execute()) {
      $this->context->addViolation($constraint->duplicate, [
        '%label' => $persona->admin_label->value,
      ]);
    }
  }

}
