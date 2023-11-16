<?php

namespace Drupal\collection_projects\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\collection\Entity\CollectionItem;

/**
 * Base class for CollectionContext Entity Reference Selection plugins.
 */
class CollectionContextTermSelectionBase extends SelectionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The identifier used as the attribute.
   *
   * @var string
   */
  protected $identifier = '';

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The collection projects manager.
   *
   * @var \Drupal\collection_projects\CollectionProjectsManager
   */
  protected $collectionProjectsManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->collectionProjectsManager = $container->get('collection_projects.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['collection_context']['help'] = [
      '#markup' => '<p>' . $this->t('Uses the collection context to determine which project focus areas should be displayed.') . '</p>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    $options = [];
    $configuration = $this->getConfiguration();

    // This mostly happens on the field edit form.
    if (!($configuration['entity'] instanceof CollectionItem) || $configuration['entity']->collection->isEmpty()) {
      return $options;
    }

    // Because of the way we add new collection_items, the collection entity
    // should always be referenced here.
    $vocabulary = $this->collectionProjectsManager->getCollectedVocabularyByKey($configuration['entity']->collection->entity, $this->identifier);

    if ($vocabulary === FALSE) {
      return $options;
    }

    /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $terms = $term_storage->loadTree($vocabulary->id(), 0, NULL, TRUE);

    foreach ($terms as $term) {
      $options[$term->bundle()][$term->id()] = $term->label();
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function countReferenceableEntities($match = NULL, $match_operator = 'CONTAINS') {
    $result = [];

    foreach ($this->getReferenceableEntities($match, $match_operator) as $vocabulary => $terms) {
      foreach ($terms as $tid => $term) {
        $result[] = $tid;
      }
    }

    return count($result);
  }

  /**
   * {@inheritdoc}
   */
  public function validateReferenceableEntities(array $ids) {
    $result = [];

    foreach ($this->getReferenceableEntities() as $vocabulary => $terms) {
      foreach ($terms as $tid => $term) {
        $result[] = $tid;
      }
    }

    return $result;
  }

}
