<?php

namespace Drupal\ilr\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Component\Utility\Xss;

/**
 * Provides a 'RemoteHtmlSnippetBlock' block.
 *
 * @Block(
 *  id = "remote_html_snippet_block",
 *  admin_label = @Translation("Remote html snippet block"),
 * )
 */
class RemoteHtmlSnippetBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'url' => '',
      'xpath' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL'),
      '#default_value' => $this->configuration['url'],
      '#maxlength' => 256,
      '#size' => 64,
      '#weight' => '0',
    ];
    $form['xpath'] = [
      '#type' => 'textfield',
      '#title' => $this->t('xpath'),
      '#default_value' => $this->configuration['xpath'],
      '#maxlength' => 256,
      '#size' => 64,
      '#weight' => '0',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['url'] = $form_state->getValue('url');
    $this->configuration['xpath'] = $form_state->getValue('xpath');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    // Fetch (and cache) the remote URL.
    $cid = 'remote_html_url_content:' . $this->configuration['url'];
    $html_content_cache_item = \Drupal::cache()->get($cid);

    if ($html_content_cache_item) {
      $html_content = $html_content_cache_item->data;
    }
    else {
      // Add a random string to avoid the realpath cache. If this doesn't work,
      // consider switching to curl.
      $html_content = file_get_contents($this->configuration['url'] . '?' . mt_rand());
      \Drupal::cache()->set($cid, $html_content, Cache::PERMANENT);
    }

    // Check whether there was any data returned. At times, such as if there is
    // an SSL error, then the data on the cache is set to `FALSE`.
    if (empty($html_content)) {
      // Add logging or find a more elegant way to handle the error?
      return $build;
    }

    // Pass the remote HTML into DOM.
    $document = new \DOMDocument('1.0', 'UTF-8');
    // Disable XML errors;.
    $internalErrors = libxml_use_internal_errors(TRUE);
    $document->loadHTML($html_content);
    // Restore XML error settings;.
    libxml_use_internal_errors($internalErrors);

    // Grab the snippet via xpath.
    $xpath = new \DOMXpath($document);
    $elements = $xpath->query($this->configuration['xpath']);

    // Add the remote snippet to the build array.
    if (!is_null($elements) && $elements->count() > 0) {
      $first_element = $elements->item(0);
      $build['remote_html_snippet'] = [
        '#markup' => $document->saveHTML($first_element),
        '#allowed_tags' => ['a', 'form', 'label', 'input', 'button', 'span'] + Xss::getAdminTagList(),
      ];
    }

    return $build;
  }

}
