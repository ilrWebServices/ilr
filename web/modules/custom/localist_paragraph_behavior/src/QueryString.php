<?php

namespace Drupal\localist_paragraph_behavior;

/**
 * Generate query strings that allow duplicate key names.
 *
 * For when PHP's http_build_query() just doesn't cut it.
 *
 * @see https://stackoverflow.com/a/17161284
 */
class QueryString {

  /**
   * An array to hold the query string key/value pairs.
   *
   * @var array
   */
  private $parts = [];

  /**
   * Add the given key as a value to the query string.
   *
   * @param string $key
   *   The key.
   * @param string $value
   *   The value.
   */
  public function add($key, $value) {
    $this->parts[] = [
      'key'   => $key,
      'val' => $value,
    ];
  }

  /**
   * Build the query string.
   *
   * @param string $separator
   *   The separator for the generated query string.
   * @param string $equals
   *   The `equals` character for the generated query string.
   */
  public function build($separator = '&', $equals = '=') {
    $query_string = [];

    foreach ($this->parts as $part) {
      $query_string[] = str_replace(['%5B', '%5D'], ['[', ']'], urlencode($part['key'])) . $equals . urlencode($part['val']);
    }

    return implode($separator, $query_string);
  }

  /**
   * Return the string content of the generated QueryString.
   */
  public function __toString() {
    return $this->build();
  }

}
