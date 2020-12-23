<?php

namespace Drupal\person;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of Person entities.
 *
 * @ingroup person
 */
class PersonListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Name');
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
      '#url' => Url::fromRoute('entity.persona.collection', ['person' => $entity->id()]),
    ];
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity) {
    $options = [];
    $options['query'] = [
      'person' => $entity->id(),
    ];

    $operations['add_persona'] = [
      'title' => $this->t('Add persona'),
      'weight' => -10,
      'url' => $this->ensureDestination(Url::fromRoute('entity.persona.add_page', [], $options)),
    ];

    $operations += parent::getOperations($entity);

    return $operations;
  }

}
