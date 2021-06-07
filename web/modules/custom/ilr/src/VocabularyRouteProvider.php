<?php

namespace Drupal\ilr;

use Drupal\taxonomy\Entity\Routing\VocabularyRouteProvider as CoreVocabularyRouteProvider;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\Routing\Route;

/**
 * Override the taxonomy VocabularyRouteProvider.
 *
 * @see ilr_entity_type_alter().
 */
class VocabularyRouteProvider extends CoreVocabularyRouteProvider {

  /**
   * {@inheritdoc}
   *
   * Create the edit form route even though we removed the `edit-form` link
   * template in ilr_entity_type_alter().
   */
  protected function getEditFormRoute(EntityTypeInterface $entity_type) {
    $entity_type_id = $entity_type->id();
    $route = new Route('/admin/structure/taxonomy/manage/{taxonomy_vocabulary}');
    // Use the edit form handler, if available, otherwise default.
    $operation = 'default';
    if ($entity_type->getFormClass('edit')) {
      $operation = 'edit';
    }
    $route
      ->setDefaults([
        '_entity_form' => "{$entity_type_id}.{$operation}",
        '_title_callback' => '\Drupal\Core\Entity\Controller\EntityController::editTitle',
      ])
      ->setRequirement('_entity_access', "{$entity_type_id}.update")
      ->setOption('parameters', [
        $entity_type_id => ['type' => 'entity:' . $entity_type_id],
      ]);

    // Entity types with serial IDs can specify this in their route
    // requirements, improving the matching process.
    if ($this->getEntityTypeIdKeyType($entity_type) === 'integer') {
      $route->setRequirement($entity_type_id, '\d+');
    }
    return $route;
  }

}
