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
 * Determines a blog collection item category based on a list of legacy terms.
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
 *     plugin: term_collection_category
 * @endcode
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 *
 * @MigrateProcessPlugin(
 *   id = "term_collection_category"
 * )
 */
class TermCollectionCategory extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Term storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $termStorage;

  /**
   * TermCollectionCategory constructor.
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
    $vid = 'blog_' . $collection_id . '_categories';

    // Scheinman blog
    if ($collection_id === 14) {
      $category = 'About Scheinman';
    }

    // ICS
    if ($collection_id === 24) {
      $category = 'About ICS';
    }

    // Buffalo Co-Lab
    if ($collection_id === 35) {
      $category = 'Co-Lab News';

      if (in_array('High Road News', $tags)) {
        $category = 'High Road';
      }
      elseif (in_array('democracy buff', $tags)) {
        $category = 'Careers in Public Service';
      }
      elseif (in_array('visiting scholar buffalo', $tags)) {
        $category = 'Visiting Scholars';
      }
      elseif (in_array('buff econ geo', $tags)) {
        $category = 'Good DEEDS';
      }
    }

    // Labor Dynamics Institute
    if ($collection_id === 36) {
      $category = 'LDI News';

      // Addtional checks for events.
      if ($this->in_array_any(['event', '2020', '2019', '2018', '2017', '2016', '2015'], $tags)) {
        $category = 'LDI Events';
      }
    }

    // Worker Institute
    if ($collection_id === 10) {
      $category = 'News';
    }

    // News
    if ($collection_id === 26) {
      $category = 'About ILR';

      // Big hash of old terms to new category.
      $category_hash = <<<EOT
        alumni: Alumni
        'alumni profile': Alumni
        'Groat Award': Alumni
        'Susan Bisom-Rapp ’83': Alumni
        'advance magazine': Alumni
        'international alumni': Alumni
        reunion: Alumni
        alimni: Alumni
        gift: Alumni
        diversity: 'Diversity and Inclusion'
        'Gender Pay Gap': 'Diversity and Inclusion'
        'gender gap': 'Diversity and Inclusion'
        faculty: Faculty
        'Faculty award': Faculty
        'faculty research': Faculty
        'Brian Lucas': Faculty
        'Emerging Scholars': Faculty
        dean: Faculty
        'Lisa Nishii': Faculty
        experts: Faculty
        'ILR faculty news': Faculty
        'Kate Griffith': Faculty
        'Rosemary Batt': Faculty
        'Alexander Colvin': Faculty
        'alex colvin': Faculty
        'new faculty': Faculty
        'Harry Katz': Faculty
        outreach: 'Public Impact'
        'Public Engagement': 'Public Impact'
        'Public Engagement Channel 2': 'Public Impact'
        'Public Engagement Channel': 'Public Impact'
        'Public Engagement Practical Stories': 'Public Impact'
        'social impact': 'Public Impact'
        research: Research
        research-page-top: Research
        research-notice: Research
        'ILR Review': Research
        'research associates': Research
        report: Research
        Student: Students
        students: Students
        'Student Experience': Students
        'current students': Students
        'Study Abroad': Students
        'McPherson Awards': Students
        Career: Students
        'student research': Students
        'prospective students': Students
        'Global Learning': Students
        debate: Students
        'student organizations': Students
        'unon days': Students
        'service learning': Students
        'student academic': Students
        'Democracy Fellows': Students
        'featured student': Students
        'peer mentor': Students
        'incoming students': Students
        EOT;

      $map = Yaml::parse($category_hash);

      // Map the new category strings to incoming tags.
      $tags_mapped = str_replace(array_keys($map), array_values($map), $tags);

      // Remove any tags that are not in the list of new categories.
      $tags_mapped = array_intersect(array_values($tags_mapped), array_values($map));

      if (!empty($tags_mapped)) {
        $category = reset($tags_mapped);
      }
    }

    // Look up the category by name and return the tid.
    $existing_terms = $this->termStorage->loadByProperties([
      'vid' => $vid,
      'name' => $category,
    ]);

    if ($existing_terms) {
      $existing_term = reset($existing_terms);
      return $existing_term->id();
    }

    // Create a new term and return the tid.
    $term = $this->termStorage->create([
      'vid' => $vid,
      'name' => $category,
    ]);
    $term->save();
    return $term->id();
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

}
