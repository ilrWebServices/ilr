<?php

namespace Drupal\ilr;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Symfony\Component\HttpFoundation\Request;

/**
 * Processes paths for ILR.
 */
class PathProcessor implements OutboundPathProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], Request $request = NULL, BubbleableMetadata $bubbleable_metadata = NULL) {
    if (isset($options['entity'])) {
      $entity = $options['entity']->getEntityTypeId() === 'collection_item' ? $options['entity']->item->entity : $options['entity'];

      // Check that we are viewing, and not editing or deleting, an entity.
      // $options['route']->getDefault('_entity_view') is present on most
      // entities by default, but node overrides the default entity route
      // provider and uses a controller for access instead. So we use the
      // `_entity_access` requirement, since most entities use the `.view`
      // convention.
      $view = preg_match('/\.view$/', $options['route']->getRequirement('_entity_access'));

      // Change the path for entities with external links to that external URL.
      if ($view && $entity instanceof ContentEntityInterface && $entity->hasField('field_external_link') && !$entity->field_external_link->isEmpty() ) {
        // See https://drupal.stackexchange.com/a/295256/58785
        $options['base_url'] = $entity->field_external_link->first()->getUrl()->toString();
        $path = '';
      }
    }

    return $path;
  }

}
