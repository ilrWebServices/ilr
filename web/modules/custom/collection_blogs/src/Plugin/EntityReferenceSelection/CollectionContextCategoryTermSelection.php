<?php

namespace Drupal\collection_blogs\Plugin\EntityReferenceSelection;

/**
 * Implementation of a CollectionContextTerm Entity Reference Selection plugin.
 *
 * @EntityReferenceSelection(
 *   id = "collection_context_category_term",
 *   label = @Translation("Collection context category term"),
 *   entity_types = {"taxonomy_term"},
 *   group = "collection_context_category_term",
 *   weight = 0
 * )
 */
class CollectionContextCategoryTermSelection extends CollectionContextTermSelectionBase {

  protected $identifier = 'blog_taxonomy_categories';

}
