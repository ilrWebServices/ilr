<?php

namespace Drupal\person;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * @ingroup person
 */
class PersonPersonasListBuilder extends EntityListBuilder {

  /**
   * Constructs a new PersonPersonasListBuilder object.
   *
   * @param \Drupal\person\PersonaInterface[] $personas
   *   An array of persona entities.
   */
  public function __construct(
    protected array $personas,
    EntityTypeInterface $entityType
  ) {
    $this->entityType = $entityType;
  }

  /**
   * Returns the specific persona entities added in the constructor.
   *
   * @return array
   *   An array of entity IDs.
   */
  public function load() {
    return $this->personas;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['admin_label'] = $this->t('Admin label');
    $header['type'] = $this->t('Type');
    $header['note'] = $this->t('Note');
    $header['status'] = $this->t('Published');
    $header['changed'] = $this->t('Last modified');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var Drupal\Core\Entity\ContentEntityInterface $entity */
    $row['admin_label'] = $entity->toLink();
    $row['type'] = $entity->type->entity->label();
    $row['note'] = $entity->hasField('note') ? $entity->note->value : '';
    $row['status'] = $entity->isPublished() ? $this->t('Yes') : $this->t('No');
    $row['changed'] = \Drupal::service('date.formatter')->format($entity->changed->value);
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['table']['#empty'] = $this->t('There are no personas for this person.');
    return $build;
  }

}
