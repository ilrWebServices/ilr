<?php

namespace Drupal\ilr\Plugin\WebformExporter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformExporter\DelimitedWebformExporter;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Defines a delimited text exporter with header overrides for EXPLORE.
 *
 * @WebformExporter(
 *   id = "delimited_explore",
 *   label = @Translation("Delimited text for EXPLORE (FSP)"),
 *   description = @Translation("Exports results as delimited text file, with header overrides for EXPLORE."),
 * )
 */
class ExploreDelimitedWebformExporter extends DelimitedWebformExporter {

  protected array $removals = [
    'localaddress__given_name',
    'localaddress__family_name',
    'localaddress__additional_name',
    'localaddress__organization',
    'localaddress__dependent_locality',
    'localaddress__country_code',
    'localaddress__langcode',
    'localaddress__sorting_code',
    'permanentaddress__given_name',
    'permanentaddress__family_name',
    'permanentaddress__additional_name',
    'permanentaddress__organization',
    'permanentaddress__dependent_locality',
    'permanentaddress__langcode',
    'permanentaddress__sorting_code',
    'preferredlocation__given_name',
    'preferredlocation__family_name',
    'preferredlocation__additional_name',
    'preferredlocation__organization',
    'preferredlocation__address_line1',
    'preferredlocation__address_line2',
    'preferredlocation__postal_code',
    'preferredlocation__dependent_locality',
    'preferredlocation__country_code',
    'preferredlocation__langcode',
    'preferredlocation__sorting_code',
    'alternatelocation__given_name',
    'alternatelocation__family_name',
    'alternatelocation__additional_name',
    'alternatelocation__organization',
    'alternatelocation__address_line1',
    'alternatelocation__address_line2',
    'alternatelocation__postal_code',
    'alternatelocation__dependent_locality',
    'alternatelocation__country_code',
    'alternatelocation__langcode',
    'alternatelocation__sorting_code',
  ];

  protected array $removals_keys = [];

  protected array $replacements = [
    'created' => 'Date changed',
    'completed' => 'Date submitted',
    'form_type' => 'Form Type',
    'localaddress__address_line1' => 'localStreet1',
    'localaddress__address_line2' => 'localStreet2',
    'localaddress__locality' => 'localCity',
    'localaddress__administrative_area' => 'localState',
    'localaddress__postal_code' => 'localZip',
    'permanentaddress__address_line1' => 'permanentStreet1',
    'permanentaddress__address_line2' => 'permanentStreet2',
    'permanentaddress__locality' => 'permanentCity',
    'permanentaddress__administrative_area' => 'permanentState',
    'permanentaddress__postal_code' => 'permanentZip',
    'permanentaddress__country_code' => 'permanentCountry',
    // These are used if the location fields are 'Advanced address' elements.
    // 'preferredlocation__locality' => 'city1',
    // 'preferredlocation__administrative_area' => 'state1',
    // 'alternatelocation__locality' => 'city2',
    // 'alternatelocation__administrative_area' => 'state2',
    'preferredlocation__city' => 'city1',
    'preferredlocation__state_province' => 'state1',
    'alternatelocation__city' => 'city2',
    'alternatelocation__state_province' => 'state2',
    'year_in_school' => 'schoolYear',
  ];

  /**
   * {@inheritdoc}
   */
  protected function buildHeader() {
    $header = parent::buildHeader();

    foreach ($this->removals as $header_to_remove) {
      $key = array_search($header_to_remove, $header);
      if ($key !== FALSE) {
        $this->removals_keys[] = $key;
        unset($header[$key]);
      }
    }

    foreach ($this->replacements as $current_header_name => $new_header_name) {
      $key = array_search($current_header_name, $header);

      if ($key !== FALSE) {
        $header[$key] = $new_header_name;
      }
    }

    return $header;
  }


  /**
   * {@inheritdoc}
   */
  protected function buildRecord(WebformSubmissionInterface $webform_submission) {
    $record = parent::buildRecord($webform_submission);

    foreach ($this->removals_keys as $key_to_remove) {
      unset($record[$key_to_remove]);
    }

    return $record;
  }

}
