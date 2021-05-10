<?php

namespace Drupal\ilr\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\ckeditor\CKEditorPluginContextualInterface;
use Drupal\editor\Entity\Editor;

/**
 * Defines the "ILR Word Paste" plugin.
 *
 * @CKEditorPlugin(
 *   id = "ilr_word_paste",
 *   label = @Translation("ILR Word Paste Filter"),
 *   module = "ilr"
 * )
 */
class WordPaste extends CKEditorPluginBase implements CKEditorPluginContextualInterface {

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return drupal_get_path('module', 'ilr') . '/js/plugins/word-paste/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    return [];
  }

  /**
   * @inheritDoc
   */
  public function getButtons() {
    return [];
  }

  /**
   * @inheritDoc
   */
  public function isEnabled(Editor $editor) {
    return TRUE;
  }

}
