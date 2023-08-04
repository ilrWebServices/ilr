<?php

namespace Drupal\ilr_migrate\Plugin\migrate\source;

use Drupal\migrate_source_csv\Plugin\migrate\source\CSV;
use League\Csv\Reader;

/**
 * Source for CSV files, including files stored on https.
 *
 * @MigrateSource(
 *   id = "csv_url",
 *   source_module = "ilr_migrate"
 * )
 */
class CSVUrl extends CSV {

  /**
   * Construct a new CSV reader.
   *
   * @return \League\Csv\Reader
   *   The reader.
   */
  protected function createReader() {
    if (preg_match('/^http/', $this->configuration['path'])) {
      $temp_dir = \Drupal::service('file_system')->getTempDirectory();
      $temp_filepath = $temp_dir . '/csv_url_' . md5($this->configuration['path']) . '.csv';

      // If the doesn't exist or is older than 5 minutes, download a copy to the
      // temp directory.
      if (!file_exists($temp_filepath) || time() - filemtime($temp_filepath) > 300) {
        $remote_csv = fopen($this->configuration['path'], 'r');

        if (!$remote_csv) {
          throw new \RuntimeException(sprintf('Remote file "%s" could not be opened.', $this->configuration['path']));
        }

        file_put_contents($temp_filepath, $remote_csv);
      }

      $path = $temp_filepath;
    }
    else {
      $path = $this->configuration['path'];
    }

    if (!file_exists($path)) {
      throw new \RuntimeException(sprintf('File "%s" was not found.', $path));
    }
    $csv = fopen($path, 'r');
    if (!$csv) {
      throw new \RuntimeException(sprintf('File "%s" could not be opened.', $path));
    }
    return Reader::createFromStream($csv);
  }

}
