<?php

namespace Drupal\ilr\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\StorageTransformEvent;

/**
 * Class ConfigEventSubscriber.
 */
class ConfigEventSubscriber implements EventSubscriberInterface {

  /**
   * The active storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $activeStorage;

  /**
   * The sync config storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $fileStorage;

  /**
   * Patterns to exclude from import and export.
   *
   * @var array
   */
  protected $ignorePatterns = [
    '/^block_visibility_groups\.block_visibility_group\.(subsite|publication_issue)_(\d+)$/',
    '/^block\.block\.union_marketing_menu_subsite_(\d+)$/',
    '/^core\.entity_(form|view)_display\.taxonomy_term\.blog_(\d+)_(categories|tags)\.(default|teaser)$/',
    '/^field\.field\.taxonomy_term\.blog_(\d+)_(categories|tags)\.[a-z_]+$/',
    '/^pathauto\.pattern\.blog_(\d+)_(categories|tags)_terms$/',
    '/^system\.menu\.(section|subsite)-(\d+)$/',
    '/^taxonomy\.vocabulary\.blog_(\d+)_(categories|tags)$/',
    '/^webform\.webform\.[a-z_]+$/',
  ];

  /**
   * Constructs a new ConfigEventSubscriber object.
   */
  public function __construct(StorageInterface $database_storage, StorageInterface $file_storage) {
    $this->activeStorage = $database_storage;
    $this->fileStorage = $file_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ConfigEvents::STORAGE_TRANSFORM_EXPORT => 'onExportTransform',
      ConfigEvents::STORAGE_TRANSFORM_IMPORT => 'onImportTransform',
    ];
  }

  /**
   * The storage is transformed for exporting.
   *
   * Remove ignored patterns from the export, unless they exist in the file
   * storage.
   *
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The config storage transform event.
   */
  public function onExportTransform(StorageTransformEvent $event) {
    /** @var \Drupal\Core\Config\StorageInterface $storage */
    $storage = $event->getStorage();

    foreach ($this->getIgnoredActiveConfig() as $config_name) {
      // Remove the outgoing config item, preventing it from being exported.
      $storage->delete($config_name);
    }
  }

  /**
   * The storage is transformed for exporting.
   *
   * Remove ignored patterns from the import, unless they exist in the file
   * storage.
   *
   * This prevents deleting configuration during deployment and configuration
   * synchronization.
   *
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The config storage transform event.
   */
  public function onImportTransform(StorageTransformEvent $event) {
    /** @var \Drupal\Core\Config\StorageInterface $storage */
    $storage = $event->getStorage();

    foreach ($this->getIgnoredActiveConfig() as $config_name) {
      // Set the incoming value to the active store value. This makes it
      // appear to be identical, thus ignoring it.
      $storage->write($config_name, $this->activeStorage->read($config_name));
    }
  }

  /**
   * Gets the active configuration items that match the ignored patterns.
   *
   * Skips config items in the sync store (e.g. config/sync).
   */
  protected function getIgnoredActiveConfig() {
    return array_filter($this->activeStorage->listAll(), function ($config_name) {
      foreach ($this->ignorePatterns as $pattern) {
        if (preg_match($pattern, $config_name) === 1 && $this->fileStorage->exists($config_name) === FALSE) {
          return TRUE;
        }
      }
      return FALSE;
    });
  }

}
