<?php

namespace Drupal\ilr\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Url;
use Drupal\media_remote\Plugin\Field\FieldFormatter\MediaRemoteFormatterBase;

/**
 * Plugin implementation of the 'media_remote_generic' formatter.
 *
 * @FieldFormatter(
 *   id = "media_remote_generic",
 *   label = @Translation("Remote Media - Generic"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class MediaRemoteGenericFormatter extends MediaRemoteFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function getUrlRegexPattern() {
    return '/^https:\/\/(.*)/';
  }

  /**
   * {@inheritdoc}
   */
  public static function getValidUrlExampleStrings(): array {
    return [
      'https://digitalcommons.ilr.cornell.edu/*',
      'https://www.ecornell.com/keynotes/view/[id]/',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function deriveMediaDefaultNameFromUrl($url) {
    $matches = [];
    $pattern = static::getUrlRegexPattern();
    preg_match_all($pattern, $url, $matches);
    if (!empty($matches[0][0])) {
      return t('Remote file from @url', [
        '@url' => $url,
      ]);
    }
    return parent::deriveMediaDefaultNameFromUrl($url);
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    foreach ($items as $delta => $item) {
      /** @var \Drupal\Core\Field\FieldItemInterface $item */
      if ($item->isEmpty()) {
        continue;
      }
      $matches = [];
      $pattern = static::getUrlRegexPattern();
      preg_match_all($pattern, $item->value, $matches);
      if (empty($matches[1][0])) {
        continue;
      }
      $elements[$delta] = [
        '#title' => $item->value,
        '#type' => 'link',
        '#url' => Url::fromUri($item->value),
      ];
    }
    return $elements;
  }

}

