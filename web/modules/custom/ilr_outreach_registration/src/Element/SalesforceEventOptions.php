<?php

namespace Drupal\ilr_outreach_registration\Element;

use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html as HtmlUtility;
use Drupal\Core\Render\Element\CompositeFormElementTrait;
use Drupal\ilr_outreach_registration\EventOption;

/**
 * Provides a 'salesforce_event_options' element.
 *
 * @FormElement("salesforce_event_options")
 *
 * Properties:
 * - #eventids: A semi-colon delimited list of Salesforce event/class object IDs.
 * - #empty: String to display if there are no options available.
 *
 * Usage example:
 * @code
 * $form['settings']['eventid'] = array(
 *   '#type' => 'salesforce_event_options',
 *   '#title' => $this->t('Events'),
 *   '#eventids' => 'a0iPK000000bfQnYAI;a0iPK000000jQ81YAE',
 *   '#empty' => $this->t('No events are available.'),
 * );
 * @endcode
 */
class SalesforceEventOptions extends FormElement {

  use CompositeFormElementTrait;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = static::class;
    return [
      '#input' => TRUE,
      '#process' => [
        [$class, 'processSalesforceEventOptions'],
      ],
      '#theme_wrappers' => ['radios'],
      '#pre_render' => [
        [$class, 'preRenderCompositeFormElement'],
      ],
    ];
  }

  /**
   * Generate individual radio options for the given eventids.
   */
  public static function processSalesforceEventOptions(&$element, FormStateInterface $form_state, &$complete_form) {
    $eventids = array_map('trim', explode(';', $element['#eventids']));
    $events = self::getSalesforceEventOptions($eventids);

    if (count($events)) {
      $weight = 0;

      foreach ($events as $key => $option) {
        // Maintain order of options as defined in #eventids.
        $weight += 0.001;

        $element += [$key => []];
        // Generate the parents as the autogenerator does, so we will have a
        // unique id for each radio button.
        $parents_for_id = array_merge($element['#parents'], [$key]);
        $element[$key] += [
          '#type' => 'radio',
          '#title' => $option->label,
          // The key is sanitized in Drupal\Core\Template\Attribute during output
          // from the theme function.
          '#return_value' => $key,
          // Use default or FALSE. A value of FALSE means that the radio button is
          // not 'checked'.
          '#default_value' => $element['#default_value'] ?? FALSE,
          '#attributes' => $element['#attributes'],
          '#parents' => $element['#parents'],
          '#id' => HtmlUtility::getUniqueId('edit-' . implode('-', $parents_for_id)),
          '#ajax' => $element['#ajax'] ?? NULL,
          // Errors should only be shown on the parent radios element.
          '#error_no_message' => TRUE,
          '#weight' => $weight,
          '#disabled' => $option->disabled,
        ];
      }
    }
    else {
      $empty_message = $element['#empty'] ?? '';

      if ($empty_message) {
        $element += ['empty' => []];
        $element['empty'] += [
          '#markup' => $empty_message,
        ];
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input !== FALSE) {
      // When there's user input (including NULL), return it as the value.
      // However, if NULL is submitted, FormBuilder::handleInputElement() will
      // apply the default value, and we want that validated against #options
      // unless it's empty. (An empty #default_value, such as NULL or FALSE, can
      // be used to indicate that no radio button is selected by default.)
      if (!isset($input) && !empty($element['#default_value'])) {
        $element['#needs_validation'] = TRUE;
      }
      return $input;
    }
    else {
      // For default value handling, simply return #default_value. Additionally,
      // for a NULL default value, set #has_garbage_value to prevent
      // FormBuilder::handleInputElement() converting the NULL to an empty
      // string, so that code can distinguish between nothing selected and the
      // selection of a radio button whose value is an empty string.
      $value = $element['#default_value'] ?? NULL;
      if (!isset($value)) {
        $element['#has_garbage_value'] = TRUE;
      }
      return $value;
    }
  }

  /**
   * Get detailed options for EXECED_Event_Class__c objects.
   *
   * @param array $eventids
   *   An array of Salesforce object ID strings, which should represent
   *   instances of EXECED_Event_Class__c objects.
   *
   * @return \Drupal\ilr_outreach_registration\EventOption[]
   *   An array of EventOption objects.
   */
  protected static function getSalesforceEventOptions(array $eventids): array {
    $cid = 'ilr_outreach_registration_options:' . md5(serialize($eventids));

    if ($cache = \Drupal::cache()->get($cid)) {
      return $cache->data;
    }

    $options = [];

    try {
      /** @var \Drupal\salesforce\Rest\RestClientInterface $sfapi */
      $sfapi = \Drupal::service('salesforce.client');

      $query = new \Drupal\salesforce\SelectQuery('EXECED_Event_Class__c');
      $query->fields = [
        'Id',
        'Name',
        'Start__c',
        'Delivery_Method__c',
      ];
      // @todo Ensure that SFIDs are formatted correctly to prevent an error.
      $query->addCondition('Id', $eventids, 'IN');

      $sf_results = $sfapi->query($query);
    }
    catch (\Exception $e) {
      \Drupal::logger('ilr_outreach_registration')->error($e->getMessage());
      return $options;
    }

    foreach ($sf_results->records() as $sfid => $record) {
      $date = new \DateTime($record->field('Start__c'));
      $location = strpos($record->field('Delivery_Method__c'), 'Online') !== FALSE ? 'Online' : 'In person';
      $options[$sfid] = new EventOption($date->format('n/j/Y') . ' - ' . $location);
    }

    uksort($options, function($sfid1, $sfid2) use ($eventids) {
      return (array_search($sfid1, $eventids) <=> array_search($sfid2, $eventids));
    });

    // Cache these options for 6 hours.
    \Drupal::cache()->set($cid, $options, time() + 60 * 60 * 6);

    return $options;
  }

}
