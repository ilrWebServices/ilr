<?php

namespace Drupal\simple_columns\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\editor\Entity\Editor;

/**
 * Defines the "simplecolumns" plugin.
 *
 * @CKEditorPlugin(
 *   id = "simplecolumns",
 *   label = @Translation("Simple Columns"),
 *   module = "cke_columns"
 * )
 */
class SimpleColumns extends CKEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return \Drupal::service('extension.list.module')->getPath('simple_columns') . '/js/plugins/simplecolumns/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    return [
      'SimpleColumns' => [
        'label' => $this->t('Simple Columns'),
        'image' => \Drupal::service('extension.list.module')->getPath('simple_columns') . '/js/plugins/simplecolumns/icons/simplecolumns.png',
      ],
      'SimpleColumnBreak' => [
        'label' => $this->t('Simple Column Break'),
        'image' => \Drupal::service('extension.list.module')->getPath('simple_columns') . '/js/plugins/simplecolumns/icons/simplecolumnbreak.png',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    return [];
  }

}
