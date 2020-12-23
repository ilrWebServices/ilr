<?php

namespace Drupal\ilr\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\ContentEntityType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Root path entity bundle' condition.
 *
 * @Condition(
 *   id = "root_path_entity_bundle",
 *   label = @Translation("Root path entity bundle")
 * )
 */
class RootPathEntityBundle extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected $entityTypeBundleInfo;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\path_alias_entities\PathAliasEntities
   */
  protected $pathAliasEntities;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityTypeBundleInfo = $container->get('entity_type.bundle.info');
    $instance->pathAliasEntities = $container->get('path_alias.entities');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form['root_entity_bundles'] = [
      '#type' => 'container',
      '#description' => $this->t('Check the root path arg for the selected entity type bundles.'),
    ];

    foreach ($this->getContentEntityTypes() as $definition) {
      $form['root_entity_bundles'][$definition->id()] = [
        '#type' => 'details',
        '#open' => !empty($this->configuration['root_entity_bundles'][$definition->id()]),
        '#title' => $definition->getBundleLabel(),
      ];

      $form['root_entity_bundles'][$definition->id()]['bundles'] = [
        '#type' => 'checkboxes',
        '#options' => $this->getBundlesForEntity($definition->id()),
        '#default_value' => $this->configuration['root_entity_bundles'][$definition->id()] ?? [],
      ];
    }

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * Get all content entity type names.
   *
   * @return array
   *   The type definitions, keyed by bundle.
   */
  protected function getContentEntityTypes() {
    $content_entity_types = [];
    $entity_type_definations = $this->entityTypeManager->getDefinitions();

    foreach ($entity_type_definations as $definition) {
      if ($definition instanceof ContentEntityType) {
        $content_entity_types[$definition->id()] = $definition;
      }
    }

    return $content_entity_types;
  }

  /**
   * Get bundles for a given entity type.
   *
   * @param string $entity_type_id
   *   The entity type id, e.g. `node`.
   *
   * @return array
   *   An array of bundles, with machine names for keys and label
   *   for values.
   */
  protected function getBundlesForEntity($entity_type_id) {
    $bundles = [];

    foreach ($this->entityTypeBundleInfo->getBundleInfo($entity_type_id) as $bundle_key => $bundle_info) {
      $bundles[$bundle_key] = (string) $bundle_info['label'];
    }

    return $bundles;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $bundles = [];
    foreach ($form_state->getValue('root_entity_bundles') as $entity_type => $bundle_info) {
      foreach ($bundle_info['bundles'] as $bundle) {
        if ($bundle) {
          $bundles[$entity_type][$bundle] = $bundle;
        }
      }
    }

    $this->configuration['root_entity_bundles'] = $bundles;
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['root_entity_bundles' => 0] + parent::defaultConfiguration();
  }

  /**
   * Evaluates the condition and returns TRUE or FALSE accordingly.
   *
   * @return bool
   *   TRUE if the condition has been met, FALSE otherwise.
   */
  public function evaluate() {
    if (empty($this->configuration['root_entity_bundles']) && !$this->isNegated()) {
      return TRUE;
    }

    if ($path_entities = $this->pathAliasEntities->getPathAliasEntities()) {
      // The condition can only be met if there are settings for this entity
      // type.
      $root_entity = reset($path_entities);

      if (!$root_entity) {
        return FALSE;
      }

      $entity_type = $root_entity->getEntityTypeId();

      if (!isset($this->configuration['root_entity_bundles'][$entity_type])) {
        return FALSE;
      }

      // Confirm the condition is met if there is a key in the settings for this
      // entity type and bundle.
      if (array_key_exists($root_entity->bundle(), $this->configuration['root_entity_bundles'][$entity_type])) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Provides a human readable summary of the condition's configuration.
   */
  public function summary() {
    if ($this->configuration['root_entity_bundles']) {
      return $this->t('Matches the root path by entity type and bundle.');
    }
    return '';
  }

}
