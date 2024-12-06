<?php

namespace Drupal\person;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\entity_usage\EntityUsageInterface;

/**
 * @ingroup person
 */
class PersonPersonasListBuilder extends EntityListBuilder {

  protected ?EntityUsageInterface $usage;
  protected AccountProxyInterface $user;

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
    $this->user = \Drupal::currentUser();

    try {
      $this->usage = \Drupal::service('entity_usage.usage');
    }
    catch (\Exception $e) {
      $this->usage = NULL;
    }
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

    if ($this->usage && $this->user->hasPermission('access entity usage statistics')) {
      $header['used'] = $this->t('Used');
    }

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

    if ($this->usage && $this->user->hasPermission('access entity usage statistics')) {
      $usages = $this->usage->listSources($entity);
      $usage_link = Link::createFromRoute($this->t('Yes'), 'entity.persona.entity_usage', [
        'persona' => $entity->id()
      ]);
      $row['used'] = $usages ? $usage_link : $this->t('No');
    }

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
