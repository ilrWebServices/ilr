<?php

namespace Drupal\ilr;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * TBD.
 */
class ClassDiscountPriceItemList extends FieldItemList {

  use ComputedItemListTrait;

  protected $discountChecked = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    if (!$this->discountChecked) {
      $this->discountChecked = TRUE;

      if (!isset($this->list[0]) && $discount_price = $this->getDiscountPrice()) {
        $this->list[0] = $this->createItem(0, $discount_price);
      }
    }
  }

  /**
   * Get any automatic discount price for this class.
   *
   * @return float|FALSE
   */
  protected function getDiscountPrice(): float|FALSE {
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
        if ($eligible_discount->type === 'percentage') {
          $discount_amt = $class->field_price->value * $eligible_discount->value;
          return $class->field_price->value + $discount_amt;
        }
        elseif ($eligible_discount && $eligible_discount->type === 'amount') {
          $discount_amt = $eligible_discount->value;
          return $class->field_price->value + $discount_amt;
        }
      }
    }
    catch (\Exception $e) {
      // @todo Maybe log this instead of doing nothing?
    }

    return FALSE;
  }

}
