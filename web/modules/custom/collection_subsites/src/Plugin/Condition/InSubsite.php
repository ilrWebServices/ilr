<?php

namespace Drupal\collection_subsites\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\collection\Entity\CollectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an 'In subsite collection' condition.
 *
 * @Condition(
 *   id = "in_subsite",
 *   label = @Translation("In any subsite"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", required = FALSE, label = @Translation("Node")),
 *     "collection" = @ContextDefinition("entity:collection", required = FALSE, label = @Translation("Collection"))
 *   }
 * )
 */
class InSubsite extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
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
    $form['in_subsite'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('In any subsite'),
      '#default_value' => $this->configuration['in_subsite'],
      '#description' => $this->t('Check if the current collection entity is a subsite or if the current node is in a subsite collection and shares the path.'),
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['in_subsite'] = $form_state->getValue('in_subsite');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['in_subsite' => 0] + parent::defaultConfiguration();
  }

  /**
   * Evaluates the condition and returns TRUE or FALSE accordingly.
   *
   * @return bool
   *   TRUE if the condition has been met, FALSE otherwise.
   */
  public function evaluate() {
    if (empty($this->configuration['in_subsite']) && !$this->isNegated()) {
      return TRUE;
    }

    if ($collection = $this->getContextValue('collection')) {
      return $this->isSubsite($collection);
    }
    elseif ($node = $this->getContextValue('node')) {
      $node_path = $node->toUrl()->toString();
      $collection_item_storage = $this->entityTypeManager->getStorage('collection_item');
      $query = $collection_item_storage->getQuery();
      $query->condition('item__target_id', $node->id());
      $query->condition('item__target_type', $node->getEntityTypeId());
      $results = $query->execute();
      $collection_items = $collection_item_storage->loadMultiple($results);

      foreach ($collection_items as $item) {
        $collection = $item->collection->entity;
        $collection_path = $collection->toUrl()->toString();
        // Is the collection path part of this node path? If so, position should be 0.
        if ($this->isSubsite($collection) && strpos($node_path, $collection_path) === 0) {
          return TRUE;
        }
      }

    }
    return FALSE;
  }

  /**
   * Provides a human readable summary of the condition's configuration.
   */
  public function summary() {
    if ($this->configuration('in_subsite')) {
      return $this->t('In any subsite');
    }
    return '';
  }

  /**
   * Check whether a collection entity is a subsite.
   *
   * @return bool
   *   True if collection is a subsite.
   */
  protected function isSubsite(CollectionInterface $collection) {
    $collection_type = $this->entityTypeManager->getStorage('collection_type')->load($collection->bundle());
    return (bool) $collection_type->getThirdPartySetting('collection_subsites', 'contains_subsites');
  }

}
