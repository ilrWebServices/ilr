<?php

namespace Drupal\ilr_neutrals\Plugin\views\row;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsRow;
use Drupal\views\Plugin\views\row\RowPluginBase;

/**
 * Generic entity row plugin to provide a common base for all entity types.
 */
#[ViewsRow(
  id: "remote_data",
  title: new TranslatableMarkup("Remote data"),
  help: new TranslatableMarkup("Displays remote data fields with an optional template."),
  display_types: ["normal"]
)]
class RemoteDataRow extends RowPluginBase {

  /**
   * Does the row plugin support to add fields to its output.
   *
   * @var bool
   */
  protected $usesFields = TRUE;

  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['template'] = ['default' => 'ilr_neutral'];
    return $options;
  }

  /**
   * Provide a form for setting options.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $options = $this->displayHandler->getFieldLabels();

    $form['template'] = [
      '#title' => $this->t('Template'),
      '#type' => 'textfield',
      '#size' => 10,
      '#default_value' => $this->options['template'] ?? '',
      '#description' => $this->t('The template in which to render the remote data.'),
    ];

  }

  public function render($row) {
    return [
      '#theme' => $this->options['template'],
      '#view' => $this->view,
      '#options' => $this->options,
      '#content' => $row,
      '#display' => $this->view->total_rows === 1 ? 'full' : 'teaser',
    ];
  }

}
