<?php

declare(strict_types=1);

namespace Drupal\ilr_employee_position;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list controller for the ilr employee position entity type.
 */
final class ILREmployeePositionListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
    $header['persona'] = $this->t('Persona');
    $header['title'] = $this->t('Title');
    $header['department'] = $this->t('Department');
    $header['primary'] = $this->t('Primary');
    $header['status'] = $this->t('Status');
    $header['created'] = $this->t('Created');
    $header['changed'] = $this->t('Updated');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\ilr_employee_position\ILREmployeePositionInterface $entity */
    $row['id'] = $entity->id();
    $row['persona'] = $entity->get('persona')->entity->label();
    $row['title']['data'] = $entity->get('title')->view(['label' => 'hidden']);
    $row['department'] = $entity->get('department')->entity->label();
    $row['primary'] = $entity->get('primary')->value ? $this->t('Yes') : $this->t('No');
    $row['status'] = $entity->get('status')->value ? $this->t('Enabled') : $this->t('Disabled');
    $row['created']['data'] = $entity->get('created')->view(['label' => 'hidden']);
    $row['changed']['data'] = $entity->get('changed')->view(['label' => 'hidden']);
    return $row + parent::buildRow($entity);
  }

}
