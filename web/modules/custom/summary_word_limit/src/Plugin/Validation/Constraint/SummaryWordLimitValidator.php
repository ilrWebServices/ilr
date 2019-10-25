<?php

namespace Drupal\summary_word_limit\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the SummaryWordLimit constraint.
 */
class SummaryWordLimitValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    /** @var \Drupal\text\Plugin\Field\FieldType\TextWithSummaryItem $item */
    foreach ($items as $item) {

      /** @var \Drupal\field\Entity\FieldConfig $field_config */
      $field_config = $item->getFieldDefinition();

      $summary_word_limit_count = $field_config->getThirdPartySetting('summary_word_limit', 'summary_word_limit_count');

      if ($summary_word_limit_count && str_word_count($item->summary) > $summary_word_limit_count) {
        $this->context->addViolation($constraint->overWordLimit, [
          '%field_name' => $field_config->getName(),
          '%limit_count' => $summary_word_limit_count,
          '%current_count' => str_word_count($item->summary)
        ]);
      }
    }
  }

}
