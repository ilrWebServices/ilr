<?php

namespace Drupal\person;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Persona entities.
 *
 * @ingroup person
 */
class PersonaListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    $query = $this->getStorage()->getQuery()
      ->sort($this->entityType->getKey('id'));

    $person = \Drupal::service('current_route_match')->getParameter('person');
    $query->condition('person', $person->id());

    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Name');
    $header['type'] = $this->t('Type');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var \Drupal\person\Entity\Persona $entity */
    $row['name']['data'] = [
      '#type' => 'link',
      '#title' => $entity->label(),
      '#url' => $entity->toUrl(),
    ];

    $row['type']['data'] = [
      '#markup' => $entity->bundle(),
    ];

    return $row + parent::buildRow($entity);
  }

}
