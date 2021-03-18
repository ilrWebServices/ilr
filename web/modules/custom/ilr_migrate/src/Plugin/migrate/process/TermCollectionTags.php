<?php

namespace Drupal\ilr_migrate\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Determines the tags for a blog collection item from a list of legacy terms.
 *
 * The expected input value should be a comma separated list of terms, such as
 * those included when setting the `node_terms` configuration on the
 * `ilr_d7_node` source plugin.
 *
 * Example:
 *
 * @code
 * process:
 *   type:
 *     plugin: term_collection_tags
 * @endcode
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 *
 * @MigrateProcessPlugin(
 *   id = "term_collection_tags"
 * )
 */
class TermCollectionTags extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Term storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $termStorage;

  /**
   * TermCollectionTags constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param $term_storage
   *   Term storage.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityStorageInterface $term_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->termStorage = $term_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('taxonomy_term')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $tags = explode(',', $value);
    $collection_id = $row->getDestinationProperty('collection');
    $vid = 'blog_' . $collection_id . '_tags';
    $terms = [];

    // Worker Institute
    if ($collection_id === 10) {
      if (in_array('mobilizing against inequality', $tags)) {
        $terms['mobilizing against inequality'] = 0;
      }

      if ($this->in_array_any(['NLLI News', 'NLLI'], $tags)) {
        $terms['NLLI'] = 0;
      }

      if (in_array('ULI', $tags)) {
        $terms['ULI'] = 0;
      }

      if (in_array('nysprojects', $tags)) {
        $terms['New York City Projects'] = 0;
      }

      if ($this->in_array_all(['worker institute', 'students'], $tags)) {
        $terms['Students'] = 0;
      }
    }

    // News
    if ($collection_id === 26) {
      // Big hash of old terms to new tags.
      $tag_hash = <<<EOT
        lel: 'Labor and Employment Law'
        'equity at work initiative': 'Equity at Work'
        event: Events
        Webinar: Events
        webcast: Events
        conference: Events
        Workshop: Events
        Healthcare: Healthcare
        health: Healthcare
        'Health-Care Summit': Healthcare
        Heathcare: Healthcare
        'Healthcare Summit': Healthcare
        'healthcare delivery system': Healthcare
        'Yale Healthcare Conference': Healthcare
        'High Road News': 'High Road'
        'high roads': 'High Road'
        'High Road Fellowship': 'High Road'
        impact: Impact
        compensation: 'Institute for Compensation Studies'
        pay: 'Institute for Compensation Studies'
        iws: 'Institute for Workplace Studies'
        'institute for workplace studies': 'Institute for Workplace Studies'
        'IWS Colloquium Series': 'Institute for Workplace Studies'
        international: International
        'international programs': International
        ilo: International
        China: International
        'Konvitz Lecture': 'Konvitz Lecture'
        'Labor and Employment Law': 'Labor and Employment Law'
        ncrn: 'Labor Dynamics Institute'
        'labor and environment': 'Labor Leading Climate'
        LERA: LERA
        'mobilizing against inequality': 'Mobilizing Against Inequality'
        NYC: 'New York City'
        ilr-in-nyc-institute-news: 'New York City'
        'ilr in nyc': 'New York City'
        ilr-in-nyc-news: 'New York City'
        'Cornell in NYC': 'New York City'
        ilr-in-nyc-news-1: 'New York City'
        nysprojects: 'New York State'
        NLLI: NLLI
        'NLLI News': NLLI
        'precarious workforce initiative': 'Precarious Workforce'
        'Smithers Institute': 'Smithers Institute'
        smithers: 'Smithers Institute'
        'Strategic Leadership Initiative': 'Strategic Leadership'
        'Union Days': 'Union Days'
        uli: 'Union Leadership Institute'
        union: Unions
        Unions: Unions
        'union building': Unions
        worker: 'Work and Jobs'
        work: 'Work and Jobs'
        'technology and employment': 'Work and Jobs'
        jobs: 'Work and Jobs'
        LaborPress: 'Work and Jobs'
        'Migrant farm workers': 'Work and Jobs'
        Workforce: 'Work and Jobs'
        'Future of Work': 'Work and Jobs'
        labor: 'Work and Jobs'
        unemployment: 'Work and Jobs'
        JORNALER@: 'Work and Jobs'
        yti: 'Yang Tan Institute'
        edi: 'Yang Tan Institute'
        Disability: 'Yang Tan Institute'
        'disability and the workplace': 'Yang Tan Institute'
        Disabilities: 'Yang Tan Institute'
        'Yang Tan Institute': 'Yang Tan Institute'
        EOT;

      $map = Yaml::parse($tag_hash);

      // Map the new tag strings to incoming tags.
      $tags_mapped = str_replace(array_keys($map), array_values($map), $tags);

      // Remove any tags that are not in the list of new tags.
      $tags_mapped = array_intersect(array_values($tags_mapped), array_values($map));

      foreach ($tags_mapped as $tag_mapped) {
        $terms[$tag_mapped] = 0;
      }
    }

    foreach ($terms as $term_name => $term_id) {
      $existing_terms = $this->termStorage->loadByProperties([
        'vid' => $vid,
        'name' => $term_name,
      ]);

      if ($existing_terms) {
        $existing_term = reset($existing_terms);
        $terms[$term_name] = $existing_term->id();
      }
      else {
        $new_term = $this->termStorage->create([
          'vid' => $vid,
          'name' => $term_name,
        ]);
        $new_term->save();
        $terms[$term_name] = $new_term->id();
      }
    }

    return array_values($terms);
  }

  /**
   * Checks if any values exist in an array.
   *
   * @param array $needles
   *   The searched values.
   *
   * @param array $haystack
   *   The array to search.
   *
   * @return bool
   *   Returns true if any needles are found in the array, false otherwise.
   */
  protected function in_array_any($needles, $haystack) {
    return !empty(array_intersect($needles, $haystack));
  }

  /**
   * Checks if all values exist in an array.
   *
   * @param array $needles
   *   The searched values.
   *
   * @param array $haystack
   *   The array to search.
   *
   * @return bool
   *   Returns true if all needles are found in the array, false otherwise.
   */
  protected function in_array_all($needles, $haystack) {
    return empty(array_diff($needles, $haystack));
 }

}
