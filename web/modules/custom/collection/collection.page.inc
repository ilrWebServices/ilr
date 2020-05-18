<?php

/**
 * @file
 * Contains collection.page.inc.
 *
 * Page callback for Collection entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Collection templates.
 *
 * Default template: collection.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_collection(array &$variables) {
  // Add the Collection Entity to the root of the array.
  $variables['collection'] = $variables['elements']['#collection'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
