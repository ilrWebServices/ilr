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
 * Add missing publications paragraph headings.
 */
function ilr_deploy_add_missing_publication_paragraph_headings(&$sandbox) {
  $publications_paragraphs = \Drupal::entityTypeManager()->getStorage('paragraph')->loadByProperties(['type' => 'publications']);

  /** @var \Drupal\paragraphs\ParagraphInterface $publications_paragraph */
  foreach ($publications_paragraphs as $publications_paragraph) {
    if ($publications_paragraph->hasField('field_heading') && $publications_paragraph->get('field_heading')->isEmpty()) {
      $publications_paragraph->set('field_heading', 'Publications');
      $publications_paragraph->save();
    }
  }
}
