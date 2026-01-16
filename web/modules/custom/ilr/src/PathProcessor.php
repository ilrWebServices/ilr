<?php

namespace Drupal\ilr;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Processes paths for ILR.
 */
class PathProcessor implements OutboundPathProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], Request $request = NULL, BubbleableMetadata $bubbleable_metadata = NULL) {
    $entity = FALSE;

    if (!empty($options['entity'])) {
      // This may be from an older version of linkit.
      $entity = $options['entity'];
    }
    // Linkit 6.1.4 stores entity links with these two options.
    elseif (!empty($options['data-entity-type']) && !empty($options['data-entity-uuid'])) {
      $entity = \Drupal::service('entity.repository')->loadEntityByUuid($options['data-entity-type'], $options['data-entity-uuid']);
    }

    if ($entity) {
      if ($entity->getEntityTypeId() === 'collection_item') {
        $entity = $entity->item->entity;
      }

      // Check that we are viewing, and not editing or deleting, an entity.
      // $options['route']->getDefault('_entity_view') is present on most
      // entities by default, but node overrides the default entity route
      // provider and uses a controller for access instead. So we use the
      // `_entity_access` requirement, since most entities use the `.view`
      // convention.
      $view = preg_match('/\.view$/', $options['route']->getRequirement('_entity_access') ?? '');
      $url_override = FALSE;

      // Change the path for entities with external links to that external URL.
      // See https://drupal.stackexchange.com/a/295256/58785
      if ($view && $entity instanceof ContentEntityInterface && $entity->hasField('field_external_link') && !$entity->field_external_link->isEmpty() ) {
        $url_override = $entity->field_external_link->first()->getUrl()->toString();
      }

      if ($view && $entity instanceof NodeInterface && $entity->hasField('field_document') && !$entity->field_document->isEmpty() && $entity->bundle() !== 'report_summary') {
        // Since field_document is a media reference, it allows us to pass
        // responsibility to the MediaInterface processor below.
        $url_override = $entity->field_document->first()->entity->toUrl()->toString();
      }

      if ($view && $entity instanceof MediaInterface && $entity->hasField('field_media_media_remote') && !$entity->field_media_media_remote->isEmpty() ) {
        $url_override = $entity->field_media_media_remote->value;
      }

      if ($view && $entity instanceof MediaInterface && $entity->hasField('field_media_file') && !$entity->field_media_file->isEmpty() && $entity->field_media_file->entity) {
        $url_override = $entity->field_media_file->entity->createFileUrl();
      }

      // Transform links to view salesforce mapped objects so that they point to
      // the salesforce url.
      if ($options['route']->getPath() === '/admin/content/salesforce/{salesforce_mapped_object}') {
        $url_override = $entity->getSalesforceUrl();
      }

      if ($url_override) {
        $options['base_url'] = $url_override;
        $path = '';
      }
    }

    return $path;
  }

}
