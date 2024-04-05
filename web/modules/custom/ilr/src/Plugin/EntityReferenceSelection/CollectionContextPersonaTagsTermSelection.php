<?php

namespace Drupal\ilr\Plugin\EntityReferenceSelection;

/**
 * Implementation of a CollectionContextTerm Entity Reference Selection plugin.
 *
 * @EntityReferenceSelection(
 *   id = "collection_context_persona_tag_term",
 *   label = @Translation("Collection context persona tag term"),
 *   entity_types = {"taxonomy_term"},
 *   group = "collection_context_persona_tag_term",
 *   weight = 0
 * )
 */
class CollectionContextPersonaTagsTermSelection extends CollectionContextTermSelectionBase {

  /**
   * {@inheritdoc}
   */
  protected $identifier = 'collection_persona_tags';

}
