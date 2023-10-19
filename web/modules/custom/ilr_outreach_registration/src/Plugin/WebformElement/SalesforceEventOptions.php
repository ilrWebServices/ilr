<?php

namespace Drupal\ilr_outreach_registration\Plugin\WebformElement;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\webform\Plugin\WebformElementBase;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Provides a 'salesforce_event_options' element.
 *
 * @WebformElement(
 *   id = "salesforce_event_options",
 *   label = @Translation("Salesforce event options"),
 *   description = @Translation("Provides radio buttons for a set of Salesforce event IDs."),
 *   category = @Translation("Options elements"),
 * )
 */
class SalesforceEventOptions extends WebformElementBase {

  /**
   * {@inheritdoc}
   */
  protected function defineDefaultProperties() {
    $properties = [
      // Element settings.
      'eventids' => '',
      'empty' => '',
    ] + parent::defineDefaultProperties();

    // We're using the `eventid` default data from the webform reference widget
    // to _configure_ the radio options, not to _choose_ an option, so we
    // disable the `default_value` property here. This means that a default
    // radio option cannot be set for now.
    unset(
      $properties['default_value'],
      $properties['format_items'],
      $properties['format_items_html'],
      $properties['format_items_text']
    );

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function initialize(array &$element) {
    parent::initialize($element);
    $element['#eventids'] = '';
  }

  /**
   * {@inheritdoc}
   */
  public function prepare(array &$element, WebformSubmissionInterface $webform_submission = NULL) {
    parent::prepare($element, $webform_submission);

    // @todo Try to get eventids from a query param.

    // Get the eventid option from the default values of the webform reference
    // widget.
    if ($source_entity = $webform_submission->getSourceEntity()) {
      if (!$source_entity instanceof ContentEntityInterface) {
        return;
      }

      /** @var \Drupal\Core\Entity\ContentEntityInterface $source_entity */
      if ($source_entity->hasField('field_registration_form')) {
        $default_data = new Yaml;
        $default_data = $default_data->parse($source_entity->field_registration_form->default_data);
        $element['#eventids'] = $default_data['eventid'] ?? '';
      }
    }

    // @todo Make this a setting in the element.
    $element['#empty'] = $this->t('No event options available.');
  }

}
