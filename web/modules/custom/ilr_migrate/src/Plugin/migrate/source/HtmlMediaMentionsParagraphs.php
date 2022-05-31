<?php

namespace Drupal\ilr_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Custom HTML media mentions from paragraph text field source plugin.
 *
 * Available configuration keys:
 * - paragraph_ids
 *
 * @MigrateSource(
 *   id = "rich_text_paragraph_media_mentions",
 *   source_module = "ilr_migrate"
 * )
 */
class HtmlMediaMentionsParagraphs extends SqlBase {

  // https://regex101.com/r/HF9dxl/1
  protected $itemRegex = '/(?<source>.*)\s+[-–]\s+(?<date>.*)\s+(?:(?:[-–]\s+)|(?:[-–]&nbsp;))(?<expert>.*)<.*\n.*(?<url>https?:\/\/.*)".*>(?<title>.*)</mUu';

  // https://regex101.com/r/qu8ym0/1
  protected $monthSplitRegex = '/<h\d>(?:.|\n)*\s+(?<year>\d{4})<\/h\d>/mU';

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('paragraph__field_body', 'pfb')->fields('pfb', [
      'entity_id',
      'revision_id',
      'langcode',
      'delta',
      'field_body_value',
      'field_body_format',
    ]);

    $query->condition('pfb.bundle', 'rich_text');
    $query->condition('pfb.entity_id', $this->configuration['paragraph_ids'], 'IN');

    return $query;
  }

  /**
   * Perform basic text cleanup that the regexes just can't handle easily.
   *
   * - Decodes HTML entities, especially `&nbsp;`.
   * - Replaces some confusing entries that don't follow the established
   *   pattern, like 'MSN - Money, October 21 - Art Wheaton'.
   */
  protected function cleanText($text) {
    $text = html_entity_decode($text);

    return str_replace([
      'MSN - Money,',
      'Money - MSN.com,'
    ], [
      'MSN Money -',
      'MSN Money -'
    ], $text);
  }

  /**
   * Parse media mentions from some HTML markup.
   *
   * @param string $html
   *   Markup with <h3> elements for Month-Year sections and links for media
   *   mentions.
   *
   * @return array
   *   An array of media mention items.
   */
  protected function parseMarkup($html) {
    $html = strip_tags($html, '<a><p><h3><br>');
    $result = [];
    $year = '';
    $dom = new \DOMDocument();
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);

    /** @var \DOMElement $element */
    $element = $dom->getElementsByTagName('h3')->item(0);

    while ($element) {
      // Skip #text nodes.
      if ($element->nodeType !== XML_ELEMENT_NODE) {
        $element = $element->nextSibling;
        continue;
      }

      if ( $element->nodeName === 'h3') {
        $year = preg_match('/(?<year>\d{4})/mU', $element->nodeValue, $matches) ? $matches['year'] : '';
      }
      else {
        /** @var \DOMNodeList $links */
        $links = $element->getElementsByTagName('a');

        foreach ($links as $link) {
          $url = $link->getAttribute('href');
          $title = $link->nodeValue;
          $date = '';
          $expert = '';
          $prev_text = '';
          $prev_sibling = $link->previousSibling;
          $prev_sibling_count = 0;

          // Skip malformed hrefs.
          if (strpos($url, 'http') !== 0) {
            continue;
          }

          while ($prev_sibling && $prev_sibling_count < 10) {
            if ($prev_sibling->previousSibling && $prev_sibling->previousSibling->nodeName === 'a') {
              break;
            }

            // Combine the trimmed node text.
            $prev_text .= preg_replace('/^\s+|\s+$/u', '', $prev_sibling->nodeValue);
            $prev_sibling = $prev_sibling->previousSibling;
            $prev_sibling_count++;
          }

          // (?<date>[JFMASOND][a-z]+\s*\d{1,2})[^-–]+[-–]\s*(?<expert>[\w\s]*)
          // (?<date>[JFMASOND][a-z]+\s*\d{1,2})\s*[-–]\s*(?<expert>[^<]*)
          if (preg_match('/(?<date>[JFMASOND][a-z]+\s*\d{1,2})[^-–]+[-–]\s*(?<expert>[\w\s]*)/', $prev_text, $matches)) {
            $date = date_create($matches['date'] . ' ' . $year);
            $expert = $matches['expert'];
          }
          else {
            continue;
          }

          $result[] = [
            // 'debug' => $matches,
            'id' => sha1($url . $prev_text . $title),
            'source' => $prev_text,
            'date' => $date ? $date->format('Y-m-d') : '',
            'date_unix' => $date ? $date->format('U') : '',
            'expert' => $expert ?? '',
            'url' => $url,
            'title' => $title,
          ];
        }
      }

      $element = $element->nextSibling;
    }

    // array_unique is here to remove any duplicates.
    return array_unique($result, SORT_REGULAR);
  }

  /**
   * {@inheritdoc}
   */
  public function count($refresh = FALSE) {
    $count = 0;

    foreach ($this->query()->execute() as $row) {
      $count += count($this->parseMarkup($row['field_body_value']));
    }

    return $count;
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() {
    $this->prepareQuery();
    $all_items = [];

    foreach ($this->query->execute() as $row) {
      $all_items = array_merge($all_items, $this->parseMarkup($row['field_body_value']));
    }

    // print_r($all_items); die();
    return new \ArrayIterator($all_items);
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'id' => $this->t('ID'),
      'source' => $this->t('Media source'),
      'date' => $this->t('Publication date'),
      'date_unix' => $this->t('Publication date (unix time)'),
      'expert' => $this->t('Name of expert'),
      'url' => $this->t('URL'),
      'title' => $this->t('Title'),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'string',
        'max_length' => 40,
        'is_ascii' => TRUE,
      ],
    ];
  }

}
