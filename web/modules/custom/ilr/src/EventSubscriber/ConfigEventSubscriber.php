<?php

namespace Drupal\ilr\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\ConfigEvents;
use Symfony\Component\EventDispatcher\Event;
use Drupal\Core\Config\StorageTransformEvent;

/**
 * Class CollectionSubsitesSubscriber.
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
    'block.block.union_marketing_menu_subsite_',
    'block_visibility_groups.block_visibility_group.publication_issue_',
    'block_visibility_groups.block_visibility_group.subsite_',
    'core.entity_view_display.taxonomy_term.blog_',
    'core.entity_form_display.taxonomy_term.blog_',
    'field.field.taxonomy_term.blog_',
    'pathauto.pattern.blog_',
    'system.menu.subsite-',
    'system.menu.section-',
    'taxonomy.vocabulary.blog_',
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
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The config storage transform event.
   */
  public function onExportTransform(StorageTransformEvent $event) {
    /** @var \Drupal\Core\Config\StorageInterface $sync_storage */
    $sync_storage = $event->getStorage();
    $ignore_patterns = $this->ignorePatterns;

    // Filter active configuration for the ignored items.
    $ignored_config = array_filter($this->activeStorage->listAll(), function($config_name) use ($ignore_patterns) {
      foreach ($ignore_patterns as $pattern) {
        // @todo Change to regex pattern if any name collisions occur.
        if (strpos($config_name, $pattern) !== FALSE) {
          return TRUE;
        }
      }
      return FALSE;
    });

    foreach ($ignored_config as $config_name) {
      // Only export the config if it already exists in the fileStorage.
      if (!$this->fileStorage->exists($config_name)) {
        $sync_storage->delete($config_name);
      }
    }
  }

  /**
   * Remove ignored patterns from the import, unless they exist in the file
   * storage.
   *
   * This prevents deleting configuration during deployment and configuration
   * synchronization.
   *
   * @param \Drupal\Core\Config\StorageTransformEvent $event The config storage
   *   transform event.
   */
  public function onImportTransform(StorageTransformEvent $event) {
    /** @var \Drupal\Core\Config\StorageInterface $sync_storage */
    $sync_storage = $event->getStorage();
    $ignore_patterns = $this->ignorePatterns;

    // Filter active configuration for the ignored items.
    $ignored_config = array_filter($this->activeStorage->listAll(), function($config_name) use ($ignore_patterns) {
      foreach ($ignore_patterns as $pattern) {
        // @todo Change to regex pattern if any name collisions occur.
        if (strpos($config_name, $pattern) !== FALSE) {
          return TRUE;
        }
      }
      return FALSE;
    });

    foreach ($ignored_config as $config_name) {
      // Unless the config exists in the fileStorage, set the sync_storage to
      // the active store values. This makes them appear to be identical ("There
      // are no changes to import").
      if (!$this->fileStorage->exists($config_name)) {
        $sync_storage->write($config_name, $this->activeStorage->read($config_name));
      }
    }
  }

}
