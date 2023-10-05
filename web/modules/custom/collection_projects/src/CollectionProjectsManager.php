<?php

namespace Drupal\collection_projects;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\collection_tools\CollectionVocabularyTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class CollectionProjectsManager {

  use CollectionVocabularyTrait;

  // @todo Refactor to load and analyze all node types to determine if they can contain project and cache here.
  public function __construct(
    public EntityTypeManagerInterface $entityTypeManager
  ) {}

  /**
   * Determine if a Collection can contain project nodes.
   *
   * @param CollectionInterface $collection
   *   A Collection entity.
   *
   * @return boolean
   *   TRUE if the Collection can collect nodes that are marked as 'is_project'.
   */
  public function collectionCanContainProjects(CollectionInterface $collection): bool {
    /** @var \Drupal\collection\Entity\CollectionTypeInterface $collection_type */
    $collection_type = $collection->type->entity;
    $allowed_bundles = $collection_type->getAllowedEntityBundles('node', $collection);
    $allowed_node_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple($allowed_bundles['node']);

    /** @var \Drupal\node\NodeTypeInterface $node_type */
    foreach ($allowed_node_types as $node_type) {
      if ($node_type->getThirdPartySetting('collection_projects', 'is_project')) {
        return TRUE;
      }
    }

    return FALSE;
  }

  public function getProjectTypesWithLabels(): array {
    $project_types = [];

    /** @var \Drupal\node\NodeTypeInterface $node_type */
    foreach ($this->entityTypeManager->getStorage('node_type')->loadMultiple() as $bundle_name => $node_type) {
      if ($node_type->getThirdPartySetting('collection_projects', 'is_project')) {
        $project_types[$bundle_name] = $node_type->label();
      }
    }

    return $project_types;
  }

}
