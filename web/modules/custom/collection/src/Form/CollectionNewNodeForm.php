<?php

namespace Drupal\collection\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\collection\Entity\CollectionInterface;

/**
 * Class CollectionNewNodeForm.
 */
class CollectionNewNodeForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new CollectionNewNodeForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'collection_new_node_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, CollectionInterface $collection = NULL) {
    $node_type_storage = $this->entityTypeManager->getStorage('node_type');
    $content_type_options = [];

    // Show only node types the user has access to.
    foreach ($node_type_storage->loadMultiple() as $type) {
      $access = $this->entityTypeManager->getAccessControlHandler('node')->createAccess($type->id(), NULL, [], TRUE);

      if ($access->isAllowed()) {
        $content_type_options[$type->id()] = $type->label();
      }
    }

    // Set the collection to which we are adding a node. This will be used in
    // the submit handler.
    $form_state->set('collection', $collection);

    // Node label (e.g. title).
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#required' => TRUE,
    ];

    // Node bundle (e.g. content type).
    $form['bundle'] = [
      '#type' => 'radios',
      '#title' => $this->t('Type'),
      '#options' => $content_type_options,
      '#default_value' => (count($content_type_options) === 1) ? array_keys($content_type_options)[0] : [],
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add'),
      '#weight' => 10,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $collection = $form_state->get('collection');
    $node_storage = $this->entityTypeManager->getStorage('node');
    $collection_item_storage = $this->entityTypeManager->getStorage('collection_item');

    // Create the new node stub (for later editing).
    $node = $node_storage->create([
      'type' => $form_state->getValue('bundle'),
      'title' => $form_state->getValue('label'),
      'status' => FALSE,
      'uid' => \Drupal::currentUser()->id(),
    ]);

    if ($node->save()) {
      // Add the new node to the form state so that other submit handlers can
      // access it.
      $form_state->set('node', $node);
      $form_state->setRedirect('entity.node.edit_form', ['node' => $node->id()],
        ['query' => ['destination' => '/collection/' . $collection->id() . '/items']]
      );
    }

    // Add the node to this collection.
    $collection_item = $collection_item_storage->create([
      'type' => 'default',
      'collection' => $collection,
      'item' => $node,
    ]);
    $collection_item->save();
  }

}
