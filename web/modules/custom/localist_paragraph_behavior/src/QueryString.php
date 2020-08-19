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

  private $parts = [];

  public function add($key, $value) {
    $this->parts[] = [
      'key'   => $key,
      'val' => $value
    ];
  }

  public function build($separator = '&', $equals = '=') {
    $query_string = [];

    foreach($this->parts as $part) {
      $query_string[] = str_replace(['%5B', '%5D'], ['[', ']'], urlencode($part['key'])) . $equals . urlencode($part['val']);
    }

    return implode($separator, $query_string);
  }

  public function __toString() {
    return $this->build();
  }

}
