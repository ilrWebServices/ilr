<?php

namespace Drupal\collection;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\ContentEntityDeleteForm;

/**
 * A service to alter a content entity form.
 */
class CollectionContentEntityFormAlter {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  private $entityTypeManager;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  private $account;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  private $moduleHandler;

  /**
   * CollectionContentEntityFormAlter constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user.
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   The module handler service.
   */
  public function __construct(EntityTypeManager $entity_type_manager, AccountProxy $current_user, ModuleHandler $module_handler) {
    $this->entityTypeManager = $entity_type_manager;
    $this->account = $current_user;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Add collection items for this entity to the form.
   *
   * Embed collection item edit forms in any content entity (e.g. node) forms
   * if that entity is in one or more collections and if the inline_entity_form
   * module is enabled.
   *
   * @param array $form
   *   Nested array of form elements that comprise the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. The arguments that
   *   \Drupal::formBuilder()->getForm() was originally called with are
   *   available in the array $form_state->getBuildInfo()['args'].
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for this ContentEntityForm.
   */
  public function embedCollectionItems(array &$form, FormStateInterface $form_state, EntityInterface $entity) {
    // Bail if the inline_entity_form module is not enabled.
    if ($this->moduleHandler->moduleExists('inline_entity_form') === FALSE) {
      return;
    }

    // Ensure that this is an entity add/edit form, not a delete form.
    $build_info = $form_state->getBuildInfo();
    if ($build_info['callback_object'] instanceof ContentEntityDeleteForm) {
      return;
    }

    // Load all collection items that reference this entity.
    $collection_item_storage = $this->entityTypeManager->getStorage('collection_item');

    $collection_item_ids = $collection_item_storage->getQuery()
      ->condition('item__target_type', $entity->getEntityTypeId())
      ->condition('item__target_id', $entity->id())
      ->execute();

    $collection_items = $collection_item_storage->loadMultiple($collection_item_ids);

    if (count($collection_items) === 0) {
      return;
    }

    foreach ($collection_items as $collection_item) {
      $collection = $collection_item->collection->entity;

      $form['collection_items_inline']['collection_' . $collection->id()] = [
        '#type' => 'details',
        '#title' => $collection->label(),
        '#group' => 'advanced',
        '#weight' => -10,
        '#optional' => TRUE,
        '#open' => TRUE,
      ];

      $form['collection_items_inline']['collection_' . $collection->id()]['item_' . $collection_item->id()] = [
        '#type' => 'inline_entity_form',
        '#entity_type' => 'collection_item',
        '#bundle' => $collection_item->bundle(),
        '#default_value' => $collection_item,
        '#form_mode' => 'mini',
        '#save_entity' => TRUE,
        '#op' => 'edit',
        '#ief_id' => 'collection_item_subform-' . $collection_item->id(),
        '#group' => 'collection_' . $collection->id(),
        '#access' => $collection_item->access('update', $this->account),
      ];
    }

    // Set the bare minimum 'inline_entity_form' value to the form state. This is
    // required to add the proper submit handlers to the node add/edit form. See
    // inline_entity_form_form_alter() in inline_entity_form.module
    $form_state->set(['inline_entity_form'], []);
  }

}

