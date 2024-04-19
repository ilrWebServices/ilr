<?php

/**
 * @file
 * Drupal hooks provided by the Drush package.
 */

/**
 * Put the cahrs member orgs vocabulary in the cahrs collection.
 */
function ilr_deploy_collect_cahrs_member_org_vocab(array &$sandbox): string {
  $entity_manager = \Drupal::entityTypeManager();

  if ($vocabulary = $entity_manager->getStorage('taxonomy_vocabulary')->load('cahrs_member_organizations')) {
    $collection_item = $entity_manager->getStorage('collection_item')->create([
      'type' => 'default',
      'collection' => 53,
      'item' => $vocabulary,
      'canonical' => TRUE,
      'weight' => 10,
    ]);
    $collection_item->save();
  }

  return t('Collection item %id saved', ['%id' => $collection_item->id()]);
}

/**
 * Map existing CAROW news subscriptions submissions to Touchpoints.
 */
function ilr_deploy_map_carow_news_to_touchpoints(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');

  $submissions = $entity_type_manager->getStorage('webform_submission')->loadByProperties([
    'webform_id' => 'carow_newsletter_signup',
  ]);

  /** @var \Drupal\Webform\WebformSubmissionInterface $submission */
  foreach ($submissions as $submission) {
    /** @var \Drupal\salesforce_mapping\Entity\MappedObjectInterface $mapped_object */
    $mapped_object = $entity_type_manager->getStorage('salesforce_mapped_object')->create([
      'salesforce_mapping' => 'info_req_tp_carow',
    ]);
    $mapped_object->setDrupalEntity($submission);

    try {
      $mapped_object->push();
    }
    catch (\Exception $e) {
      // Fail silently.
    }
  }
}

/**
 * Add initial 'Organizational unit' terms.
 */
function ilr_deploy_add_org_unit_terms(&$sandbox) {
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $entity_repository = \Drupal::service('entity.repository');

  $terms = [
    'College & Unit Industrial and Labor Relations',
    'Program Delivery Facilitation',
    "Dean's Office",
    'Economics-ILR',
    'College of Agriculture and Life Sciences',
    'Buffalo Co-Lab',
    'CAROW',
    'Conflict Resolution',
    'CJI',
    'Human Capital Development',
    'Institute for Compensation Studies',
    'Ithaca Co-Lab',
    'Labor and Unions',
    'Outreach Strategic Initiatives Incubator',
    'Workforce Industry and Economic Development',
    'Yang-Tan Institute on Employment and Disability',
    'Global Labor and Work',
    'Human Resource Studies',
    'Human Resources Management',
    'Cornell in Buffalo',
    'Associate Dean of Outreach',
    'Cornell Forensics Department',
    'Economics',
    'Employment & Labor Law',
    'Extension Administration',
    'Admissions Office',
    'Alumni Affairs and Develop',
    'Budget Office',
    'Climate Jobs Institute',
    'Center for Applied Research on Work',
    'Collect Barg Law & History',
    'Ctr Advanced HRS',
    'Center for Advanced HR Studies',
    'Department of Global Labor and Work',
    'EMHRM',
    'Employee Relations Internal Investigations and Employment Law',
    'Executive Education',
    'Facilities',
    'Fiscal Operations',
    'Global Labor Institute',
    'Graduate Programs',
    'Human Resources',
    'Human Resources DEPT',
    'Human Resources SUBD',
    'International and Comparative Labor',
    'International Programs',
    'Ithaca Conference Center',
    'Labor and Employment Law Program',
    'Labor Economics',
    'Labor Dynamics Institute',
    'Marketing & Communications',
    'NYC Conference Center',
    'NYC Technology Services',
    'New Conversations Project',
    'Office of Career Services',
    'Office of Off-Campus Credit Programs',
    'Office of Student Services',
    'Organizational Behavior',
    'Registrar SUBD',
    'Resident Administration',
    'Review',
    'Scheinman Institute',
    'Smithers',
    'Social Statistics',
    'Statistics and Data Science',
    'Technology Services',
    'Web Team',
    'Worker Institute',
    'Worker Rights Institute',
    'Labor Education & Research',
    'Registrar',
    'Office of the Dean',
    'Outreach NYC Administration',
    'External Centers and Institutes',
    'Labor Relations Law and History',
    'Law',
    'Digitization and Conservation Services',
    'Digital Scholar & Pres Svcs',
    'Catherwood Kheel',
    'Catherwood Library',
    'Hospitality Labor & Management Library',
    'Library Special Collections',
    'Teaching Research Outreach & Learning Services',
    'NYC Marketing & Communications',
    'Office of ILR Associate Dean of Outreach',
    "Provost's Office",
  ];

  if ($vocab = $entity_repository->loadEntityByUuid('taxonomy_vocabulary', '6a9255f8-414c-4b09-9c7c-90a8b772ebf2')) {
    foreach ($terms as $term_name) {
      $term = $entity_type_manager->getStorage('taxonomy_term')->create([
        'name' => $term_name,
        'vid' => $vocab->id(),
      ]);
      $term->save();
    }
  }
}
