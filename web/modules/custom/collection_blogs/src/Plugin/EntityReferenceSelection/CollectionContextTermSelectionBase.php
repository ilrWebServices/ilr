<?php

namespace Drupal\collection_blogs\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for CollectionContext Entity Reference Selection plugins.
 */
class CollectionContextTermSelectionBase extends SelectionPluginBase implements ContainerFactoryPluginInterface {

  protected $identifier = '';

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['collection_context']['help'] = [
      '#markup' => '<p>' . $this->t('Uses the collection context to determine which blog categories should be displayed.') . '</p>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    $options = [];
    $collection_item = $this->getConfiguration()['entity'];
    $collection = $collection_item->collection->entity;

    if (!$collection) {
      return $options;
    }

    $collection_items = $collection->findItems('taxonomy_vocabulary');
    $term_manager = $this->entityTypeManager->getStorage('taxonomy_term');

    foreach ($collection_items as $collection_item) {
      if ($collection_item->getAttribute($this->identifier) === FALSE) {
        continue;
      }

      $vocab = $collection_item->item->entity;
      $terms = $term_manager->loadTree($vocab->id(), 0, NULL, TRUE);

      foreach ($terms as $term) {
        $options[$term->bundle()][$term->id()] = $term->label();
      }
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
