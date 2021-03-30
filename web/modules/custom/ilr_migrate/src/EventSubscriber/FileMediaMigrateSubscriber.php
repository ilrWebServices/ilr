<?php

namespace Drupal\ilr_migrate\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\focal_point\FocalPointManagerInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber for ILR file migration events.
 */
class FileMediaMigrateSubscriber implements EventSubscriberInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\focal_point\FocalPointManagerInterface definition.
   *
   * @var \Drupal\focal_point\FocalPointManagerInterface
   */
  protected $focalPointManager;

  /**
   * EntityRedirectMigrateSubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\focal_point\FocalPointManagerInterface $focal_point_manager
   *   The focal point manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FocalPointManagerInterface $focal_point_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->focalPointManager = $focal_point_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      MigrateEvents::POST_ROW_SAVE => 'onPostRowSave',
    ];
  }

  /**
   * Updates focal point crops based on D7 focal point values.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *   The migrate post row save event.
   */
  public function onPostRowSave(MigratePostRowSaveEvent $event) : void {
    if ($event->getMigration()->getDestinationPlugin()->getPluginId() !== 'entity:media') {
      return;
    }

    $row = $event->getRow();
    $source = $row->getSource();
    $id = $event->getDestinationIdValues();
    $media_entity_id = reset($id);

    // Since 50,50 is the default value and D8 focal point crops are created
    // automatically with that value, we don't need to set them again.
    if (empty($source['focal_point']) || $source['focal_point'] === '50,50') {
      return;
    }

    if ($media_entity = $this->entityTypeManager->getStorage('media')->load($media_entity_id)) {
      if ($media_entity->bundle() !== 'image') {
        return;
      }

      if ($media_entity->field_media_image->isEmpty()) {
        return;
      }

      $image_properties = $media_entity->field_media_image->first()->getValue();
      $focal_point_crop = $this->focalPointManager->getCropEntity($media_entity->field_media_image->entity, 'focal_point');
      list($x, $y) = explode(',', $source['focal_point']);
      $this->focalPointManager->saveCropEntity($x, $y, $image_properties['width'], $image_properties['height'], $focal_point_crop);
    }
  }

}
