<?php

namespace Drupal\ilr_migrate\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber for ILR import events.
 */
class MigrateImportEventSubscriber implements EventSubscriberInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface;
   */
  protected $configFactory;

  /**
   * MigrateImportEventSubscriber constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface
   *   The config factory interface.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      MigrateEvents::PRE_IMPORT => 'onPreImport',
      MigrateEvents::POST_IMPORT => 'onPostImport',
    ];
  }

  /**
   * Handle events before importing.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The migrate pre imprt event.
   */
  public function onPreImport(MigrateImportEvent $event) : void {
    if ($event->getMigration()->getDestinationPlugin()->getPluginId() !== 'entity:collection_item') {
      return;
    }

    if ($redirect_config = $this->configFactory->getEditable('redirect.settings')) {
      $redirect_config->set('auto_redirect', FALSE);
      $redirect_config->save();
    }
  }

  /**
   * Handle events after importing.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The migrate pre imprt event.
   */
  public function onPostImport(MigrateImportEvent $event) : void {
    if ($event->getMigration()->getDestinationPlugin()->getPluginId() !== 'entity:collection_item') {
      return;
    }

    if ($redirect_config = $this->configFactory->getEditable('redirect.settings')) {
      $redirect_config->set('auto_redirect', TRUE);
      $redirect_config->save();
    }
  }
}
