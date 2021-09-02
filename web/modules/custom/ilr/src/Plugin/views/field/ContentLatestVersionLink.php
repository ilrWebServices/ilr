<?php

namespace Drupal\ilr\Plugin\views\field;

use Drupal\views\Plugin\views\field\LinkBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\ResultRow;

/**
 * Field handler to present a link to an entity's latest version.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("content_latest_version_link")
 */
class ContentLatestVersionLink extends LinkBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $row) {
    return $this->getEntity($row) ? parent::render($row) : [];
  }

  /**
   * {@inheritdoc}
   */
  protected function renderLink(ResultRow $row) {
    if ($this->options['output_url_as_text']) {
      return $this->getUrlInfo($row)->toString();
    }
    return parent::renderLink($row);
  }

  /**
   * {@inheritdoc}
   */
  protected function getUrlInfo(ResultRow $row) {
    $entity = $this->getEntity($row);

    /** @var \Drupal\content_moderation\ModerationInformationInterface $moderation_info */
    $moderation_info = \Drupal::service('content_moderation.moderation_information');

    $template = $moderation_info->hasPendingRevision($entity) ? 'latest-version' : 'canonical';

    return $entity->toUrl($template);
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['output_url_as_text'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['output_url_as_text'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Output the URL as text'),
      '#default_value' => $this->options['output_url_as_text'],
    ];
    parent::buildOptionsForm($form, $form_state);
    // Only show the 'text' field if we don't want to output the raw URL.
    $form['text']['#states']['visible'][':input[name="options[output_url_as_text]"]'] = ['checked' => FALSE];
  }

  /**
   * Override the default label.
   *
   * @return string
   *   The default link label.
   */
  protected function getDefaultLabel() {
    return $this->t('Latest version');
  }
}
