<?php

namespace Drupal\collection_resource_library\Plugin\EntityReferenceSelection;

/**
 * Implementation of a CollectionContextTerm Entity Reference Selection plugin.
 *
 * @EntityReferenceSelection(
 *   id = "collection_context_research_topics_term",
 *   label = @Translation("Collection context research topics term"),
 *   entity_types = {"taxonomy_term"},
 *   group = "collection_context_research_topics_term",
 *   weight = 0
 * )
 */
class CollectionContextResearchTopicsTermSelection extends CollectionContextTermSelectionBase {

  /**
   * {@inheritdoc}
   */
  protected $identifier = 'research_lib_topics';

}
