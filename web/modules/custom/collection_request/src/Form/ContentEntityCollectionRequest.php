<?php

namespace Drupal\collection_request\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\collection\CollectionContentManager;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\collection\Event\CollectionEvents;
use Drupal\collection\Event\CollectionItemFormSaveEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Undocumented class
 */
class ContentEntityCollectionRequest extends FormBase {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The collection content manager service.
   *
   * @var \Drupal\collection\CollectionContentManager
   */
  protected $collectionContentManager;

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
   * Create a new ContentEntityCollectionRequest form.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\collection\CollectionContentManager
   *   The collection content manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current user account.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface;
   *   The symfony event dispatcher.
   */
  public function __construct(EntityTypeManager $entity_type_manager, CollectionContentManager $collection_content_manager, AccountProxyInterface $account, EventDispatcherInterface $event_dispatcher) {
    $this->entityTypeManager = $entity_type_manager;
    $this->collectionContentManager = $collection_content_manager;
    $this->account = $account;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      $container->get('entity_type.manager'),
      $container->get('collection.content_manager'),
      $container->get('current_user'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'content_entity_collection_request';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity = NULL, array $existing_collection_items = []) {
    $form = [];

    if (!$entity) {
      return $form;
    }

    // $form_state->set('entity', $entity);
    $existing_collections = [];

    foreach ($existing_collection_items as $existing_collection_item) {
      $existing_collections[] = $existing_collection_item->collection->target_id;
    }

    // Set the node for which we are requesting the collection be added. This
    // will be used in the submit handler.
    $form_state->set('collection_item_type', '¯\_(ツ)_/¯');

    // Get a list of collections. Access control will ensure that the list only
    // shows collections that the current user can view.
    foreach ($this->collectionContentManager->getAvailableCollections($entity, 'view') as $cid => $collection) {
      if ($existing_collection_items && in_array($cid, $existing_collections)) {
        continue;
      }

      // Don't offer to request addition to self.
      if ($entity === $collection) {
        continue;
      }

      $options[$cid] = $collection->label();
    }

    if (empty($options)) {
      return $form;
    }

    // Sort the options alphabetically by label.
    asort($options);

    $form['collection_id'] = [
      '#type' => 'select',
      '#title' => t('Add to'),
      '#options' => $options,
      '#default_value' => '',
      '#js_select' => FALSE,
    ];

    $form['note'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Note to owner'),
      '#maxlength' => 255,
      '#default_value' => '',
      '#placeholder' => $this->t('Optionally add a reason or suggest a category'),
    ];

    $form['request'] = [
      '#type' => 'submit',
      '#value' => t('Request'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $form_state->getBuildInfo()['args'][0];
    $entity_type_manager = \Drupal::service('entity_type.manager');
    $collection_item_storage = $entity_type_manager->getStorage('collection_item');
    $collection_id = $form_state->getValue('collection_id');
    $collection_item_type = $form_state->get('collection_item_type');
    $collection_storage = $entity_type_manager->getStorage('collection');
    $collection = $collection_storage->load($collection_id);

    // Check if the type was set in a presubmit hook. Otherwise, use the first
    // available option.
    if ($collection_item_type === '¯\_(ツ)_/¯') {
      $allowed_types = $collection->type->entity->getAllowedCollectionItemTypes($entity->getEntityTypeId(), $entity->bundle());
      $collection_item_type = reset($allowed_types);
    }

    $attributes[] = [
      'key' => 'collection-request-uid',
      'value' => $this->account->id(),
    ];

    // Check if the requester included a note.
    if ($note = $form_state->getValue('note')) {
      $attributes[] = [
        'key' => 'collection-request-note',
        'value' => $note,
      ];
    }

    $collection_item = $collection_item_storage->create([
      'type' => $collection_item_type,
      'collection' => $collection,
      'item' => $entity,
      'canonical' => FALSE,
      'attributes' => $attributes,
    ]);

    if ($collection_item->save()) {
      $form_state->set('collection_item', $collection_item);
      $event = new CollectionItemFormSaveEvent($collection_item, SAVED_NEW);
      $this->eventDispatcher->dispatch(CollectionEvents::COLLECTION_ITEM_FORM_SAVE, $event);
    }

    $this->messenger()->addMessage(t('Collection request for %entity added to %collection.', [
      '%entity' => $entity->label(),
      '%collection' => $collection->label()
    ]));
  }

}
