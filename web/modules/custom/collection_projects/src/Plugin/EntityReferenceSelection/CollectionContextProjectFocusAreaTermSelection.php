<?php

namespace Drupal\collection_projects\Plugin\EntityReferenceSelection;

/**
 * Implementation of a CollectionContextTerm Entity Reference Selection plugin.
 *
 * @EntityReferenceSelection(
 *   id = "collection_context_project_focus_area_term",
 *   label = @Translation("Collection context focus area term"),
 *   entity_types = {"taxonomy_term"},
 *   group = "collection_context_project_focus_area_term",
 *   weight = 0
 * )
 */
class CollectionContextProjectFocusAreaTermSelection extends CollectionContextTermSelectionBase {

  /**
   * {@inheritdoc}
   */
  protected $identifier = 'project_taxonomy_focus_areas';

}
