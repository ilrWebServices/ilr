<?php

namespace Drupal\collection_blogs\Plugin\EntityReferenceSelection;

/**
 * Implementation of a CollectionContext Entity Reference Selection plugin.
 *
 * @EntityReferenceSelection(
 *   id = "collection_context_tag_term",
 *   label = @Translation("Collection context tag term"),
 *   entity_types = {"taxonomy_term"},
 *   group = "collection_context_tag_term",
 *   weight = 0
 * )
 */
class CollectionContextTagTermSelection extends CollectionContextTermSelectionBase {

  protected $identifier = 'blog_taxonomy_tags';

}
