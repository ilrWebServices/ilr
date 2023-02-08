<?php

namespace Drupal\ilr;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * TBD.
 */
class ClassDiscountDateItemList extends FieldItemList {

  use ComputedItemListTrait;

  protected $discountChecked = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    if (!$this->discountChecked) {
      $this->discountChecked = TRUE;

      if (!isset($this->list[0]) && $discount_date = $this->getDiscountDate()) {
        $this->list[0] = $this->createItem(0, $discount_date);
      }
    }
  }

  /**
   * Undocumented function
   *
   * @return void
   */
  protected function getDiscountDate(): array|FALSE {
    /** @var \Drupal\ilr\Entity\ClassNodeInterface */
    $class = $this->getEntity();

    if (!($env_disount_codes = getenv('ILR_DISCOUNT_CODES'))) {
      return FALSE;
    }

    if ($class->field_price->isEmpty()) {
      return FALSE;
    }

    if (!$mapped_object = $class->getClassNodeSalesforceMappedObject()) {
      return FALSE;
    }

    $env_disount_codes = explode(';', $env_disount_codes);

    try {
      $discount_manager = \Drupal::service('ilr_outreach_discount_manager');

      // @todo Revisit if/when multiple discount codes are allowed.
      $eligible_discount = $discount_manager->getCachedEligibleDiscount($env_disount_codes[0], $mapped_object->salesforce_id->getString());

      if ($eligible_discount) {
        return [
          'value' => $eligible_discount->startDate->format('Y-m-d'),
          'end_value' => $eligible_discount->endDate->format('Y-m-d'),
        ];
      }
    }
    catch (\Exception $e) {
      // @todo Maybe log this instead of doing nothing?
    }

    return NULL;
  }

}
