<?php

namespace Drupal\ilr_migrate\Plugin\migrate\source;

use Drupal\file\Plugin\migrate\source\d7\File;
use Drupal\migrate\Row;

/**
 * Drupal 7 file source from database with customizations.
 *
 * - Added file type support.
 *
 * @MigrateSource(
 *   id = "ilr_d7_file",
 *   source_module = "file"
 * )
 */
class IlrFile extends File {

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return parent::fields() + [
      'type' => $this->t('The type of the file (from file_entity module.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    if (in_array($row->getSourceProperty('type'), ['document', 'undefined'])) {
      $row->setSourceProperty('type', 'file');
    }
    elseif ($row->getSourceProperty('type') === 'video') {
      if (strpos($row->getSourceProperty('uri'), 'youtube://') === 0) {
        $row->setSourceProperty('type', 'remote_video');

        // Transform the uri value to the full youtube URL. It will then be used
        // as the remote video value.
        $new_uri = str_replace('youtube://v/', 'https://www.youtube.com/watch?v=', $row->getSourceProperty('uri'));
        $row->setSourceProperty('uri', $new_uri);
      }
    }
    return parent::prepareRow($row);
  }

}
