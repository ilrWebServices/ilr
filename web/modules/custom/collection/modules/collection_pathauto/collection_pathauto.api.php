<?php

/**
 * @file
 * Documentation for collection pathauto API.
 */

/**
 * Alter Pathauto-generated aliases for canonical collections before saving.
 *
 * @param string $alias The automatic alias after token replacement and strings
 *   cleaned.
 * @param array $context An associative array of additional options, with the
 *   following elements:
 *   - 'module': The module or entity type being aliased.
 *   - 'op': A string with the operation being performed on the object being
 *     aliased. Can be either 'insert', 'update', 'return', or 'bulkupdate'.
 *   - 'source': A string of the source path for the alias (e.g. 'node/1'). This
 *     can be altered by reference.
 *   - 'data': An array of keyed objects to pass to token_replace().
 *   - 'type': The sub-type or bundle of the object being aliased.
 *   - 'language': A string of the language code for the alias (e.g. 'en'). This
 *     can be altered by reference.
 *   - 'pattern': A string of the pattern used for aliasing the object.
 *   - 'original_alias': The alias that existed before it was altered by the
 *     collection module.
 *   - 'collection_item': The collection item being saved.
 *   - 'canonical_collection': The canonical collection to which this item
 *     belongs.
 */
function hook_collection_pathauto_alias_alter(&$alias, array &$context) {
  // Alter the pathauto pattern to include a referenced term url from the
  // collection item.
  if (isset($context['canonical_collection']) && isset($context['collection_item'])) {
    if ($context['collection_item']->hasField('field_term_reference') && !$context['collection_item']->field_term_reference->isEmpty()) {
      $term_alias = $context['collection_item']->field_term_reference->entity->toUrl()->toString();
      $alias = $term_alias . $alias;
    }
  }
}
