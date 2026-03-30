<?php

namespace Drupal\ilr_employee_data\Plugin\EmbeddedContent;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\embedded_content\EmbeddedContentInterface;
use Drupal\embedded_content\EmbeddedContentPluginBase;
use Drupal\ilr_employee_data\CreativeWorkEmbeddedContentPluginBase;
use Spatie\SchemaOrg\Schema;

/**
 * Plugin ??
 *
 * @EmbeddedContent(
 *   id = "ilr_employee_data.book",
 *   label = @Translation("Book"),
 *   description = @Translation("Renders a Book."),
 * )
 */
class Book extends CreativeWorkEmbeddedContentPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $default_config = parent::defaultConfiguration();
    return $default_config;
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $this->schemaObject = Schema::book()
      ->publisher(Schema::organization()->name((string) $this->configuration['publisher']));

    return parent::build();
  }

}
