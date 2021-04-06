<?php

namespace Drupal\ilr_migrate;

trait InArrayMulti {

  /**
   * Checks if any values exist in an array.
   *
   * @param array $needles
   *   The searched values.
   *
   * @param array $haystack
   *   The array to search.
   *
   * @return bool
   *   Returns true if any needles are found in the array, false otherwise.
   */
  protected function in_array_any($needles, $haystack) {
    return !empty(array_intersect($needles, $haystack));
  }

  /**
   * Checks if all values exist in an array.
   *
   * @param array $needles
   *   The searched values.
   *
   * @param array $haystack
   *   The array to search.
   *
   * @return bool
   *   Returns true if all needles are found in the array, false otherwise.
   */
  protected function in_array_all($needles, $haystack) {
    return empty(array_diff($needles, $haystack));
 }

}
