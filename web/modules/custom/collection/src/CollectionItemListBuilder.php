<?php

namespace Drupal\collection;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of Collection items.
 *
 * @ingroup collection
 */
class CollectionItemListBuilder extends BulkFormEntityListBuilder {

  /** The parent collection.
   *
   * @var \Drupal\collection\Entity\CollectionInterface
   */
  protected $collection;

  /**
   * Constructs a new CollectionItemListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage.
   * @param \Drupal\Core\Entity\EntityStorageInterface $action_storage
   *   The action storage.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, EntityStorageInterface $action_storage, FormBuilderInterface $form_builder, RouteMatchInterface $route_match) {
    parent::__construct($entity_type, $storage, $action_storage, $form_builder);
    $this->collection = $route_match->getParameter('collection');
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('entity_type.manager')->getStorage('action'),
      $container->get('form_builder'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    return $this->collection->getItems();
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['item'] = $this->t('Item');
    $header['type'] = $this->t('Type');
    $header['status'] = $this->t('Published');
    $header['weight'] = $this->t('Weight');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var \Drupal\collection\Entity\CollectionItem $entity */
    $row['item'] = [
      '#type' => 'link',
      '#title' => $entity->item->entity->label(),
      '#url' => $entity->item->entity->toURL(),
    ];

    $type = $entity->item->entity->getEntityType()->getLabel();

    if ($entity->item->entity->getEntityTypeId() === 'node') {
      $type .= ': ' . node_get_type_label($entity->item->entity);
    }
    elseif ($entity->item->entity->getEntityType()->get('bundle_entity_type') !== NULL) {
      $type .= ': ' . $entity->item->entity->bundle();
    }

    $row['type'] = [
      '#markup' => $type
    ];

    if ($entity->item->entity->getEntityType()->hasKey('status')) {
      $published = ($entity->item->entity->isPublished()) ? 'Yes' : 'No';
    }
    else {
      $published = 'n/a';
    }

    $row['status'] = [
      '#markup' => $this->t($published),
    ];

    $row['weight'] = [
      '#markup' => $entity->weight->value
    ];

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function buildOperations(EntityInterface $entity) {
    $build = parent::buildOperations($entity);
    $item_entity_type = ($entity->item->entity instanceof \Drupal\Core\Entity\ContentEntityInterface) ? 'content' : 'configuration';

    // Add a link to edit the collection item referenced item.
    $build['#links'] = array_merge([
      'edit_item' => [
        'title' => $this->t('Edit @entity_type', ['@entity_type' => $item_entity_type]),
        'url' => $this->ensureDestination($entity->item->entity->toURL('edit-form')),
        'weight' => -1,
      ]
    ], $build['#links']);

    if (!empty($build['#links']['edit'])) {
      // Rename the edit link title.
      $build['#links']['edit']['title'] = $this->t('Edit collection item');
    }
    if (!empty($build['#links']['delete'])) {
      // Rename the delete link title.
      $build['#links']['delete']['title'] = $this->t('Remove from collection');
    }
    return $build;
  }

}
