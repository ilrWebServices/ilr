<?php

namespace Drupal\ilr_migrate\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Returns the expected node bundle value based on a field value.
 *
 * The d7_news_bundle process plugin changes the default node bundle based on the existence of a field value in D7.
 *
 * Available configuration keys:
 * - default_value: The fixed default value to apply.
 *
 * Example:
 *
 * @code
 * process:
 *   type:
 *     plugin: d7_news_bundle
 *     default_value: post
 * @endcode
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 *
 * @MigrateProcessPlugin(
 *   id = "d7_news_bundle"
 * )
 */
class D7NewsBundle extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $external_url = $row->get('field_website_url');
    return !empty($external_url) ? 'media_mention' : $this->configuration['default_value'];
  }

}
