<?php

namespace Drupal\ilr\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the UniqueMediaMentionExternalLink constraint.
 */
class UniqueMediaMentionExternalLinkValidator extends ConstraintValidator {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   *
   * @var Drupal\Core\Field\FieldItemList $field_external_link
   */
  public function validate($field_external_link, Constraint $constraint) {
    if ($field_external_link->isEmpty()) {
      return;
    }

    $entity = $field_external_link->getEntity();

    // If editing existing entity and there's no change to the field, skip
    // validation.
    if (!$entity->isNew()) {
      $original_entity = \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId())->loadUnchanged($entity->id());

      if ($field_external_link->uri === $original_entity->field_external_link->uri) {
        return;
      }
    }

    $query = \Drupal::entityTypeManager()->getStorage('node')->getQuery();
    $query->accessCheck(TRUE);
    $query->condition('field_external_link', $field_external_link->uri);

    // Don't include self when updating a media mention.
    if ($nid = $entity->id()) {
      $query->condition('nid', $nid, '!=');
    }

    if ($result = $query->execute()) {
      $this->context->addViolation($constraint->duplicate, [
        '%url' => $field_external_link->uri,
        '@create_crosspost_url' => Url::fromRoute('collection.node.collections', [
          'node' => reset($result),
        ])->toString(),
      ]);
    }
  }

}
