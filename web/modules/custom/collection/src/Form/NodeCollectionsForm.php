<?php

namespace Drupal\collection\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\collection\Event\CollectionEvents;
use Drupal\collection\Event\CollectionItemFormCreateEvent;
use Drupal\Core\Link;

class NodeCollectionsForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs a new NodeCollectionsForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface;
   *   The symfony event dispatcher.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher) {
    $this->entityTypeManager = $entity_type_manager;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'collections_node_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    $collections_storage = $this->entityTypeManager->getStorage('collection');
    $options = [];
    $default = [];

    // Set the node for which we are editing collections. This will be used in
    // the submit handler.
    $form_state->set('node', $node);

    // Get a list of collections. Access control will ensure that the list only
    // shows collections that the current user can modify.
    foreach ($collections_storage->loadMultiple() as $id => $collection) {
      $options[$id] = ['name' => Link::createFromRoute($collection->label(), 'entity.collection_item.collection', ['collection' => $id])->toString()];
      $default[$id] = ($collection->getItem($node)) ? TRUE : FALSE;
    }

    $form['collections'] = [
      '#type' => 'tableselect',
      '#empty' => $this->t('No collections have been created yet.'),
      '#header' => ['name' => $this->t('Collection')],
      '#description' => $this->t('Select the collections in which to place this node.'),
      '#options' => $options,
      '#default_value' => $default,
      '#js_select' => FALSE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $node = $form_state->get('node');
    $collection_options = $form_state->getValue('collections');
    $collections_storage = $this->entityTypeManager->getStorage('collection');
    $collections = $collections_storage->loadMultiple(array_keys($collection_options));

    foreach ($collection_options as $collection_id => $value) {
      if ($value) {
        // Add to collection if not already there.
        if ($collection_item = $collections[$collection_id]->addItem($node)) {
          $this->messenger()->addMessage($this->t('Added %entity to %collection.', [
            '%entity' => $node->label(),
            '%collection' => $collections[$collection_id]->label(),
          ]));

          // Dispatch new collection item form event.
          $event = new CollectionItemFormCreateEvent($collection_item);
          $this->eventDispatcher->dispatch(CollectionEvents::COLLECTION_ITEM_FORM_CREATE, $event);
        }
      }
      else {
        // Remove from collection.
        if ($collections[$collection_id]->removeItem($node)) {
          $this->messenger()->addMessage($this->t('Removed %entity from %collection.', [
            '%entity' => $node->label(),
            '%collection' => $collections[$collection_id]->label()
          ]));
        }
      }
    }
  }

}
