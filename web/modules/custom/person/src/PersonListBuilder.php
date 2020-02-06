<?php

namespace Drupal\person;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

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
    /* @var \Drupal\person\Entity\Person $entity */
    $row['name']['data'] = [
      '#type' => 'link',
      '#title' => $entity->label(),
      '#url' => $entity->toUrl(),
    ];
    return $row + parent::buildRow($entity);
  }

}
