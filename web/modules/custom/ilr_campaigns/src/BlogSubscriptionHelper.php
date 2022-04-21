<?php

namespace Drupal\ilr_campaigns;

use CampaignMonitor\CampaignMonitorRestClient;
use Drupal\collection\CollectionContentManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drupal\webform\Entity\WebformSubmission;
use Psr\Log\LoggerInterface;

/**
 * The Blog Subscription helper service.
 */
class BlogSubscriptionHelper extends ListManagerBase {

  /**
   * The Campaign Montitor REST Client.
   *
   * @var \CampaignMonitor\CampaignMonitorRestClient
   */
  protected $client;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The queue factory service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The ILR campaigns configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $settings;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;


  /**
  * The collection content manager service.
  *
  * @var \Drupal\collection\CollectionContentManager
  */
  protected $collectionContentManager;

  /**
   * The list ID setting.
   */
  protected $listIdSettingName = 'blog_updates_list_id';

  /**
   * The custom field name.
   */
  protected $customFieldName = 'SubscriberInterests';

  /**
   * The webform id.
   */
  protected $webformId = 'blog_updates';

  /**
   * Constructs a new Blog subscription service object.
   *
   * @param \CampaignMonitor\CampaignMonitorRestClient $campaign_monitor_rest_client
   *   The Campaign Monitor rest client.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\collection\CollectionContentManager $collection_content_manager
   *   The collection content manager service.
   */
  public function __construct(CampaignMonitorRestClient $campaign_monitor_rest_client, EntityTypeManagerInterface $entity_type_manager, StateInterface $state, QueueFactory $queue_factory, ConfigFactoryInterface $config_factory, LoggerInterface $logger, CollectionContentManager $collection_content_manager) {
    parent::__construct($campaign_monitor_rest_client, $entity_type_manager, $state, $queue_factory, $config_factory, $logger);
    $this->collectionContentManager = $collection_content_manager;
  }

  /**
   * Get blog options for the custom field.
   *
   * @return array
   *   An array of the options.
   */
  protected function getCustomFieldOptions() {
    $collection_storage = $this->entityTypeManager->getStorage('collection');
    $options = [];

    // Load bloggish collections.
    $ids = $collection_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', ['blog', 'subsite_blog'], 'IN')
      ->execute();

    // Create options for each bloggish collection.
    foreach ($collection_storage->loadMultiple($ids) as $collection) {
      $options[] = $this->getOptionName($collection);
    }

    return $options;
  }

  /**
   * @inheritDoc
   */
  protected function getCustomFieldValue(WebformSubmission $submission) {
    $source_entity = $submission->getSourceEntity();
    $source_entity_type = $source_entity->getEntityTypeId();

    switch ($source_entity_type) {
      case 'collection':
        $collection = $source_entity;
        break;
      case 'collection_item':
        $collection = $source_entity->collection->entity;
        break;
      case 'node':
      case 'taxonomy_term':
        $collection = $this->collectionContentManager->getCanonicalCollectionForEntity($source_entity);
        break;
      default:
        return FALSE;
    }

    return $collection->label();
  }

}
