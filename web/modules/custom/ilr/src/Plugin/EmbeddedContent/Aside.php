<?php

namespace Drupal\ilr\Plugin\EmbeddedContent;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\embedded_content\EmbeddedContentInterface;
use Drupal\embedded_content\EmbeddedContentPluginBase;

/**
 * Defines an Aside embedded content plugin.
 *
 * @EmbeddedContent(
 *   id = "ilr.aside",
 *   label = @Translation("Aside"),
 *   description = @Translation("Renders an Aside."),
 * )
 */
class Aside extends EmbeddedContentPluginBase implements EmbeddedContentInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'content' => NULL,
    ];
  }

    /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['content'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Aside content'),
      '#format' => $this->configuration['content']['format'] ?? 'simple_formatting',
      '#default_value' => $this->configuration['content']['value'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build = [
      '#type' => 'inline_template',
      '#template' => '<aside class="sidebar">{{ content|raw }}</aside>',
      '#context' => [
        'content' => $this->configuration['content']['value'],
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function isInline(): bool {
    return FALSE;
  }

}
