<?php

namespace Drupal\simple_columns\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\ckeditor\CKEditorPluginConfigurableInterface;
use Drupal\Core\Form\FormStateInterface;
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
    return drupal_get_path('module', 'simple_columns') . '/js/plugins/simplecolumns/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    return [
      'SimpleColumns' => [
        'label' => $this->t('Simple Columns'),
        'image' => drupal_get_path('module', 'simple_columns') . '/js/plugins/simplecolumns/icons/simplecolumns.png',
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
