<?php

namespace Drupal\ilr_neutrals\Plugin\views\row;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Template\Attribute;
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

    $options['component'] = ['default' => 'ilr_neutrals:ilr-neutral'];
    return $options;
  }

  /**
   * Provide a form for setting options.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $options = $this->displayHandler->getFieldLabels();

    $form['component'] = [
      '#title' => $this->t('Component'),
      '#type' => 'textfield',
      '#size' => 10,
      '#default_value' => $this->options['component'] ?? '',
      '#description' => $this->t('The component in which to render the remote data.'),
    ];
  }

  public function render($row) {
    $props = (array) $row;
    $props['display'] = $this->view->total_rows === 1 ? 'full' : 'teaser';
    // dump($props['websites']);

    if (!empty($props['websites'])) {
      $websites = explode('|', trim($props['websites']));

      foreach ($websites as $key => $website) {
        $website = trim($website);

        if (empty($website)) {
          unset($websites[$key]);
        }
        elseif (!preg_match('|^https?://|', $website)) {
          $websites[$key] = 'http://' . $website;
        }
      }

      $props['websites'] = $websites;
    }

    $props['attributes'] = new Attribute([
      'data-arbid' => $row->id,
      'data-state' => $row->state,
    ]);

    return [
      '#type' => 'component',
      '#component' => $this->options['component'],
      '#props' => $props,
    ];
  }

}
