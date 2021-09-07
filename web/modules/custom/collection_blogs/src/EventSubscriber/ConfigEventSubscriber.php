<?php

namespace Drupal\collection_blogs\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\StorageTransformEvent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Subscriber for config importing and exporting.
 */
class ConfigEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new ConfigEventSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger) {
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ConfigEvents::STORAGE_TRANSFORM_IMPORT => 'onImportTransform',
    ];
  }

  /**
   * Transform the storage during import.
   *
   * Whitelist all editorial-related config for auto-generated vocabularies.
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
    $editorial_workflow = $storage->read('workflows.workflow.editorial');

    if (!$editorial_workflow) {
      return;
    }

    // Load all the vocabularies.
    $vocabularies = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->loadMultiple();
    $original_term_config = $editorial_workflow['type_settings']['entity_types']['taxonomy_term'] ?? [];

    foreach($vocabularies as $vocab) {
      if (strpos($vocab->id(), 'blog_') === 0) {
        $editorial_workflow['type_settings']['entity_types']['taxonomy_term'][] = $vocab->id();
      }
    }

    if (empty($editorial_workflow['type_settings']['entity_types']['taxonomy_term'])) {
      return;
    }

    $editorial_workflow['type_settings']['entity_types']['taxonomy_term'] = array_unique($editorial_workflow['type_settings']['entity_types']['taxonomy_term']);
    sort($editorial_workflow['type_settings']['entity_types']['taxonomy_term']);

    if ($original_term_config === $editorial_workflow['type_settings']['entity_types']['taxonomy_term']) {
      return;
    }

    $storage->write('workflows.workflow.editorial', $editorial_workflow);
    $this->messenger->addMessage($this->t('Editorial workflow configuration for blog categories enforced.'));
  }

}
