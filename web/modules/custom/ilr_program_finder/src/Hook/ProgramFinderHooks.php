<?php

namespace Drupal\ilr_program_finder\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ProgramFinderHooks {

  public function __construct(
    #[Autowire(service: 'search_api.entity_datasource.tracking_manager')]
    protected ContentEntityTrackingManager $trackingManager,
  ) {}

  /**
   * Implements hook_entity_insert().
   *
   * @see \Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager::entityInsert()
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    if ($entity instanceof NodeInterface && $entity->bundle() === 'class' && !$entity->field_course->isEmpty()) {
      $this->trackingManager->entityInsert($entity->field_course->entity);
    }
  }

  /**
   * Implements hook_entity_update().
   *
   * @see \Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager::entityUpdate()
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    if ($entity instanceof NodeInterface && $entity->bundle() === 'class' && !$entity->field_course->isEmpty()) {
      $this->trackingManager->entityUpdate($entity->field_course->entity);
    }
  }

  /**
   * Implements hook_entity_delete().
   *
   * @see \Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager::entityDelete()
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    if ($entity instanceof NodeInterface && $entity->bundle() === 'class' && !$entity->field_course->isEmpty()) {
      $this->trackingManager->entityDelete($entity->field_course->entity);
    }
  }

}
