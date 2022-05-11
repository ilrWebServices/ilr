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
  protected $regex = '/(?<source>.*)\s+[-–]\s+(?<date>.*)\s+(?:(?:[-–]\s+)|(?:[-–]&nbsp;))(?<expert>.*)<.*\n.*(?<url>https?:\/\/.*)".*>(?<title>.*)</mU';

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
   * {@inheritdoc}
   */
  public function count($refresh = FALSE) {
    $count = 0;

    foreach ($this->query()->execute() as $row) {
      preg_match_all($this->regex, $row['field_body_value'], $matches, PREG_SET_ORDER);
      $count += count($matches);
    }

    return $count;
    // return (int) $this->query()->countQuery()->execute()->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() {
    $this->prepareQuery();

    $all_matches = [];

    foreach ($this->query->execute() as $row) {
      preg_match_all($this->regex, $row['field_body_value'], $matches, PREG_SET_ORDER);

      foreach ($matches as $key => $match) {
        // Remove the numeric keys from each match and overwrite it.
        $matches[$key] = array_filter($match, 'is_string', ARRAY_FILTER_USE_KEY);

        // Add a unique id so that migrate can create a mapping.
        $matches[$key]['id'] = sha1(serialize($match));
      }

      $all_matches = array_merge($all_matches, $matches);
    }

    return new \ArrayIterator($all_matches);
    // $statement = $this->query->execute();
    // $statement->setFetchMode(\PDO::FETCH_ASSOC);
    // return new \IteratorIterator($statement);
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'id' => $this->t('ID'),
      'source' => $this->t('Media source'),
      'date' => $this->t('Publication date'),
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
