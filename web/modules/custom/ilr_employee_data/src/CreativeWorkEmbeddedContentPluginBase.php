<?php

namespace Drupal\ilr_employee_data;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\embedded_content\EmbeddedContentInterface;
use Drupal\embedded_content\EmbeddedContentPluginBase;
use Drupal\multivalue_form_element\Element\MultiValue;
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
class CreativeWorkEmbeddedContentPluginBase extends EmbeddedContentPluginBase implements EmbeddedContentInterface {

  use StringTranslationTrait;

  protected $schemaObject;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'name' => NULL,
      'publisher' => NULL,
      'publication_date' => NULL,
    ];
  }

    /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->configuration['name'],
      '#required' => TRUE,
    ];

    $form['publisher'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Publisher'),
      '#default_value' => $this->configuration['publisher'],
      '#required' => TRUE,
    ];

    $form['publication_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Publication date'),
      '#default_value' => $this->configuration['publication_date'],
      '#required' => TRUE,
    ];

    $form['authors'] = [
      '#type' => 'multivalue',
      '#title' => 'Authors',
      '#cardinality' => MultiValue::CARDINALITY_UNLIMITED,
      'first_name' => [
        '#type' => 'textfield',
        '#title' => $this->t('First name'),
      ],
      'last_name' => [
        '#type' => 'textfield',
        '#title' => $this->t('Last name'),
      ],
      '#default_value' => $this->configuration['authors'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $authors = [];
    foreach ($this->configuration['authors'] as $author) {
      if (!is_array($author)) {
        continue;
      }

      $authors[] = Schema::person()
        ->givenName((string) $author['first_name'])
        ->familyName((string) $author['last_name'])
        ->additionalName((string) $author['middle_name']);
    }

    /** @var \Spatie\SchemaOrg\CreativeWork $schemaObject */
    $this->schemaObject
      ->name((string) $this->configuration['name'])
      ->author($authors)
      ->datePublished(new \DateTime($this->configuration['publication_date']));
      // ->url((string) $publication->WEB_ADDRESS)
      // ->setProperty('ai_contype', (string) $publication->CONTYPE)
      // ->setProperty('ai_public_view', (string) $publication->PUBLIC_VIEW);

    $build = [
      '#type' => 'component',
      '#component' => 'ilr_employee_data:' . strtolower($this->schemaObject->getType()),
      '#props' => $this->schemaObject->getProperties(),
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
