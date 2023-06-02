<?php

namespace Drupal\ilr\Plugin\simple_sitemap\UrlGenerator;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\simple_sitemap\Entity\EntityHelper;
use Drupal\simple_sitemap\Logger;
use Drupal\simple_sitemap\Plugin\simple_sitemap\SimpleSitemapPluginBase;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\simple_sitemap\Exception\SkipElementException;
use Drupal\simple_sitemap\Manager\EntityManager;
use Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\EntityUrlGeneratorBase;
use Drupal\simple_sitemap\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the collection item URL generator.
 *
 * @UrlGenerator(
 *   id = "entity_collection_item",
 *   label = @Translation("Collection item URL generator"),
 *   description = @Translation("Generates collection item URLs by overriding the 'entity' URL generator."),
 *   settings = {
 *     "overrides_entity_type" = "collection_item",
 *   },
 * )
 */
class EntityCollectionItemUrlGenerator extends EntityUrlGeneratorBase {

  /**
   * The simple_sitemap.entity_manager service.
   *
   * @var \Drupal\simple_sitemap\Manager\EntityManager
   */
  protected $entitiesManager;

  /**
   * EntityMenuLinkContentUrlGenerator constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\simple_sitemap\Logger $logger
   *   Simple XML Sitemap logger.
   * @param \Drupal\simple_sitemap\Settings $settings
   *   The simple_sitemap.settings service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\simple_sitemap\Entity\EntityHelper $entity_helper
   *   Helper class for working with entities.
   * @param \Drupal\simple_sitemap\Manager\EntityManager $entities_manager
   *   The simple_sitemap.entity_manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Logger $logger,
    Settings $settings,
    LanguageManagerInterface $language_manager,
    EntityTypeManagerInterface $entity_type_manager,
    EntityHelper $entity_helper,
    EntityManager $entities_manager
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $logger,
      $settings,
      $language_manager,
      $entity_type_manager,
      $entity_helper
    );
    $this->entitiesManager = $entities_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition): SimpleSitemapPluginBase {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('simple_sitemap.logger'),
      $container->get('simple_sitemap.settings'),
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('simple_sitemap.entity_helper'),
      $container->get('simple_sitemap.entity_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDataSets(): array {
    $data_sets = [];
    $sitemap_entity_types = $this->entityHelper->getSupportedEntityTypes();
    $bundle_settings = $this->entitiesManager
      ->setVariants($this->sitemap->id())
      ->getAllBundleSettings();
    if (!empty($bundle_settings[$this->sitemap->id()]['collection_item'])) {
      foreach ($bundle_settings[$this->sitemap->id()]['collection_item'] as $bundle_name => $settings) {
        if (!empty($settings['index'])) {
          $keys = $sitemap_entity_types['collection_item']->getKeys();

          $query = $this->entityTypeManager->getStorage('collection_item')->getQuery();

          if (!empty($keys['id'])) {
            $query->sort($keys['id']);
          }
          if (!empty($keys['bundle'])) {
            $query->condition($keys['bundle'], $bundle_name);
          }
          if (!empty($keys['published'])) {
            $query->condition($keys['published'], 1);
          }
          elseif (!empty($keys['status'])) {
            $query->condition($keys['status'], 1);
          }

          // Only get non-canoncical collection items.
          $query->condition('canonical', 0);

          $data_set = [
            'entity_type' => 'collection_item',
            'id' => [],
          ];
          foreach ($query->execute() as $entity_id) {
            $data_set['id'][] = $entity_id;
            if (count($data_set['id']) >= 50) {
              $data_sets[] = $data_set;
              $data_set['id'] = [];
            }
          }
          // Add the last data set if there are some IDs gathered.
          if (!empty($data_set['id'])) {
            $data_sets[] = $data_set;
          }
        }
      }
    }

    return $data_sets;
  }

  /**
   * {@inheritdoc}
   */
  protected function processDataSet($data_set): array {
    foreach ($this->entityTypeManager->getStorage($data_set['entity_type'])->loadMultiple((array) $data_set['id']) as $entity) {
      try {
        $paths[] = $this->processEntity($entity);
      }
      catch (SkipElementException $e) {
        continue;
      }
    }

    return $paths ?? [];
  }

  /**
   * Processes the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to process.
   *
   * @return array
   *   Processing result.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function processEntity(ContentEntityInterface $entity): array {
    $entity_settings = $this->entitiesManager
      ->setVariants($this->sitemap->id())
      ->getEntityInstanceSettings($entity->getEntityTypeId(), $entity->id());

    if (empty($entity_settings[$this->sitemap->id()]['index'])) {
      throw new SkipElementException();
    }

    $entity_settings = $entity_settings[$this->sitemap->id()];
    $url_object = $entity->toUrl()->setAbsolute();

    // Do not include external paths.
    if (!$url_object->isRouted()) {
      throw new SkipElementException();
    }

    return [
      'url' => $url_object,
      'lastmod' => method_exists($entity, 'getChangedTime')
      ? date('c', $entity->getChangedTime())
      : NULL,
      'priority' => $entity_settings['priority'] ?? NULL,
      'changefreq' => !empty($entity_settings['changefreq']) ? $entity_settings['changefreq'] : NULL,
        // Additional info useful in hooks.
      'meta' => [
        'path' => $url_object->getInternalPath(),
        'entity_info' => [
          'entity_type' => $entity->getEntityTypeId(),
          'id' => $entity->id(),
        ],
      ],
    ];
  }
}
