<?php

namespace Drupal\ilr_migrate\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Replaces D7 file embeds with D8 media embeds in text values.
 *
 * @code
 * process:
 *   field_body:
 *      -
 *        plugin: media_embed
 *        source: value
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "media_embed",
 *   handle_multiples = TRUE
 * )
 */
class MediaEmbed extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The migrate lookup service.
   *
   * @var \Drupal\migrate\MigrateLookupInterface
   */
  protected $migrateLookup;

  /**
   * Media entity storage.
   *
   * @var \Drupal\media\MediaStorage
   */
  protected $mediaStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration
    );

    $instance->migrateLookup = $container->get('migrate.lookup');
    $instance->mediaStorage = $container->get('entity_type.manager')->getStorage('media');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {

    // Update any embedded media. See https://regex101.com/r/K5FMNj/4 to test
    // this regex.
    if (preg_match_all('/\[\[{"fid":"(\d+)".*"link_text":"?([^",]+)"?.*\]\]/m', $value, $matches, PREG_SET_ORDER)) {
      foreach ($matches as $match) {
        $lookup_result = $this->migrateLookup->lookup('d7_file_media', [$match[1]]);

        if (!$lookup_result) {
          continue;
        }

        if ($media = $this->mediaStorage->load($lookup_result[0]['mid'])) {
          $link_text = $match[2] !== 'null' ? $match[2] : '';
          $value = str_replace($match[0], sprintf('<drupal-media data-link-text="%s" data-entity-type="media" data-entity-uuid="%s"></drupal-media>', $link_text, $media->uuid()), $value);
        }
      }
    }

    return $value;
  }

}
