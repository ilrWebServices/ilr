<?php

namespace Drupal\person;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a class to build a listing of Person entities.
 *
 * @ingroup person
 */
class PersonListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * Constructs a new PersonListBuilder object.
   */
  public function __construct(
    EntityTypeInterface $entityType,
    EntityStorageInterface $storage,
    protected Request $request
  ) {
    parent::__construct($entityType, $storage);
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Name');
    $header['changed'] = $this->t('Last modified');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\person\Entity\Person $entity */
    $row['name']['data'] = [
      '#type' => 'link',
      '#title' => $entity->label(),
      '#url' => $this->ensureDestination($entity->toUrl()),
    ];
    $row['changed'] = \Drupal::service('date.formatter')->format($entity->changed->value);
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    // Add our custom filter form to the build.
    $build['form'] = \Drupal::formBuilder()->getForm('Drupal\person\Form\PersonCollectionSearchForm');
    $build += parent::render();

    // Change the empty table message from EntityListBuilder::render().
    $build['table']['#empty'] = $this->t('No @label found.', ['@label' => $this->entityType->getPluralLabel()]);

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityListQuery(): QueryInterface {
    // We don't call $query = parent:: getEntityListQuery() because it adds a
    // sort on id, and we want our own sort on changed.
    $query = $this->getStorage()->getQuery()
      ->accessCheck(TRUE)
      ->sort('changed', 'DESC');

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }

    if ($name = $this->request->get('name')) {
      $query->condition('display_name', $name, 'CONTAINS');
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity) {
    $operations['personas'] = [
      'title' => $this->t('Personas'),
      'weight' => -10,
      'url' => Url::fromRoute('entity.person.personas', [
        'person' => $entity->id(),
      ]),
    ];

    $operations += parent::getOperations($entity);

    return $operations;
  }

}
