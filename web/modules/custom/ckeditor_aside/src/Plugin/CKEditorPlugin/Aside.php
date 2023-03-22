<?php

namespace Drupal\ckeditor_aside\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\editor\Entity\Editor;

/**
 * Defines the "aside" plugin.
 *
 * @CKEditorPlugin(
 *   id = "aside",
 *   label = @Translation("Aside"),
 *   module = "ckeditor_aside"
 * )
 */
class Aside extends CKEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return drupal_get_path('module', 'ckeditor_aside') . '/js/plugins/aside/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    return [
      'Aside' => [
        'label' => $this->t('Aside'),
        'image' => drupal_get_path('module', 'ckeditor_aside') . '/js/plugins/aside/icons/aside.png',
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
