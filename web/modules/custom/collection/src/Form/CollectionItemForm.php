<?php

namespace Drupal\collection\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\collection\Event\CollectionEvents;
use Drupal\collection\Event\CollectionItemFormCreateEvent;
use Drupal\collection\Event\CollectionItemFormSaveEvent;

/**
 * Form controller for Collection item edit forms.
 *
 * @ingroup collection
 */
class CollectionItemForm extends ContentEntityForm {

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs a new CollectionItemForm.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current user account.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface;
   *   The symfony event dispatcher.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, AccountProxyInterface $account, EventDispatcherInterface $event_dispatcher) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time, $event_dispatcher);

    $this->account = $account;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('current_user'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;

    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Collection item.', [
          '%label' => $entity->label(),
        ]));
        $event = new CollectionItemFormCreateEvent($entity);
        $this->eventDispatcher->dispatch(CollectionEvents::COLLECTION_ITEM_FORM_CREATE, $event);
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Collection item.', [
          '%label' => $entity->label(),
        ]));
    }

    $event = new CollectionItemFormSaveEvent($entity, $status);
    $this->eventDispatcher->dispatch(CollectionEvents::COLLECTION_ITEM_FORM_SAVE, $event);

    $form_state->setRedirect('entity.collection_item.collection', ['collection' => $entity->collection->target_id]);
  }

}
