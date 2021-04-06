<?php

namespace Drupal\ilr_migrate\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ilr_migrate\InArrayMulti;
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

  use InArrayMulti;

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
    $type = $row->getSourceProperty('type');
    $vid = 'blog_' . $collection_id . '_categories';

    // Scheinman blog
    if ($collection_id === 14) {
      $category = 'About Scheinman';
    }

    // ILR Student blog
    if ($collection_id === 18) {
      $category = 'Uncategorized';

      if ($this->in_array_any(['ambassador', 'student ambassador'], $tags)) {
        $category = 'Student Ambassadors';
      }
      elseif ($this->in_array_any(['dublin', 'credit internship'], $tags)) {
        $category = 'Credit Internships Archived';
      }
      elseif ($this->in_array_any(['international programs', 'ILR International Programs', 'internatonal programs'], $tags)) {
        $category = 'International Programs Archived';
      }
    }

    // Graduate Programs Blog
    if ($collection_id === 38) {
      $category = 'Uncategorized';

      if (in_array('emhrm', $tags)) {
        $category = 'EMHRM';
      }
      elseif ($this->in_array_all(['graduate programs', 'MILR'], $tags)) {
        $category = 'MILR';
      }
    }

    // ICS
    if ($collection_id === 24) {
      $category = 'About ICS';
    }

    // Buffalo Co-Lab
    if ($collection_id === 35) {
      $category = 'Co-Lab News';

      if ($this->in_array_any(['High Road News', 'high road'], $tags)) {
        $category = $type === 'experience_report' ? 'High Road Fellows' : 'High Road';
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

    // Mobilizing Against Inequality
    if ($collection_id === 37) {
      $category = 'News';

      $category_hash = <<<EOT
        'case study': 'Case Studies'
        'france': 'Countries'
        'germany': 'Countries'
        'global labor relations': 'Immigrant Rights'
        'guest worker rights': 'Immigrant Rights'
        'immigrant rights': 'Immigrant Rights'
        'immigrant worker rights': 'Immigrant Rights'
        'immigrant workers': 'Immigrant Rights'
        'immigration defense': 'Immigrant Rights'
        'migrant worker rights': 'Immigrant Rights'
        'Interview': 'Interviews'
        'journalism': 'Literature Reviews'
        'literature review': 'Literature Reviews'
        'resource': 'Resources'
        'justice in motion': 'Social Justice'
        'social justice': 'Social Justice'
        'Islamophobia': 'Social Justice'
        'uk': 'Countries'
        'union': 'Unions'
        'Unions': 'Unions'
        'usa': 'Countries'
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

    // News
    if ($collection_id === 26) {
      $category = 'About ILR';

      // Big hash of old terms to new category.
      $category_hash = <<<EOT
        'advance magazine': 'Alumni'
        'alex colvin': 'Faculty'
        'Alexander Colvin': 'Faculty'
        'alimni': 'Alumni'
        'alumni': 'Alumni'
        'alumni profile': 'Alumni'
        'Brian Lucas': 'Faculty'
        'Career': 'Students'
        'current students': 'Students'
        'dean': 'Faculty'
        'debate': 'Students'
        'Democracy Fellows': 'Students'
        'diversity': 'Diversity and Inclusion'
        'Emerging Scholars': 'Faculty'
        'experts': 'Faculty'
        'faculty': 'Faculty'
        'Faculty award': 'Faculty'
        'faculty research': 'Faculty'
        'featured student': 'Students'
        'gender gap': 'Diversity and Inclusion'
        'Gender Pay Gap': 'Diversity and Inclusion'
        'gift': 'Alumni'
        'Global Learning': 'Students'
        'Groat Award': 'Alumni'
        'Harry Katz': 'Faculty'
        'ILR faculty news': 'Faculty'
        'ILR Review': 'Research'
        'incoming students': 'Students'
        'international alumni': 'Alumni'
        'Kate Griffith': 'Faculty'
        'Lisa Nishii': 'Faculty'
        'McPherson Awards': 'Students'
        'new faculty': 'Faculty'
        'outreach': 'Public Impact'
        'peer mentor': 'Students'
        'prospective students': 'Students'
        'Public Engagement': 'Public Impact'
        'Public Engagement Channel': 'Public Impact'
        'Public Engagement Channel 2': 'Public Impact'
        'Public Engagement Practical Stories': 'Public Impact'
        'report': 'Research'
        'research': 'Research'
        'research associates': 'Research'
        'research-notice': 'Research'
        'research-page-top': 'Research'
        'reunion': 'Alumni'
        'Rosemary Batt': 'Faculty'
        'service learning': 'Students'
        'social impact': 'Public Impact'
        'Student': 'Students'
        'student academic': 'Students'
        'Student Experience': 'Students'
        'student organizations': 'Students'
        'student research': 'Students'
        'students': 'Students'
        'Study Abroad': 'Students'
        'Susan Bisom-Rapp ’83': 'Alumni'
        'unon days': 'Students'
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

}
