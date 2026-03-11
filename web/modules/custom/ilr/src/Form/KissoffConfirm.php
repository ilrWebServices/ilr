<?php

namespace Drupal\ilr\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\user\UserStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a confirmation form for performing ILR-related cleanup of users.
 *
 * @internal
 */
class KissoffConfirm extends ConfirmFormBase {

  /**
   * The temp store factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new UserMultipleCancelConfirm.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The temp store factory.
   * @param \Drupal\user\UserStorageInterface $user_storage
   *   The user storage.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, UserStorageInterface $user_storage, EntityTypeManagerInterface $entity_type_manager) {
    $this->tempStoreFactory = $temp_store_factory;
    $this->userStorage = $user_storage;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private'),
      $container->get('entity_type.manager')->getStorage('user'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'kissoff_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to perform the following cleanup tasks?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.user.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Confirm');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Retrieve the accounts from the temp store.
    /** @var \Drupal\user\Entity\User[] $accounts */
    $accounts = $this->tempStoreFactory
      ->get('ilr_user_kissoff_action')
      ->get($this->currentUser()->id());

    if (!$accounts) {
      return $this->redirect('entity.user.collection');
    }

    $items = [];
    $form['accounts'] = ['#tree' => TRUE];

    foreach ($accounts as $account) {
      $uid = $account->id();

      if ($uid <= 1) {
        continue;
      }

      $items[$uid] = [
        '#type' => 'item',
        '#title' => $account->label(),
      ];

      if ($roles = $this->getRoles($account)) {
        $items[$uid]['roles'] = [
          '#prefix' => $this->t('The following roles will be removed:'),
          '#theme' => 'item_list',
          '#items' => $roles,
        ];
      }

      if ($user_collections = $this->getUserCollections($account)) {
        $collection_labels = [];
        foreach ($user_collections as $user_collection) {
          $collection_labels[] = $user_collection->label();
        }
        $items[$uid]['collections'] = [
          '#prefix' => $this->t('The account will be removed from the following collections:'),
          '#theme' => 'item_list',
          '#items' => $collection_labels,
        ];
      }

      if ($ilr_employee_persona = $this->getEmployeePersona($account)) {
        if ($ilr_employee_persona->isPublished()) {
          $items[$uid]['ilr_employee_persona'] = [
            '#prefix' => $this->t('The account has an employee profile that will be unpublished: '),
            '#type' => 'link',
            '#title' => $ilr_employee_persona->getDisplayName(),
            '#url' => $ilr_employee_persona->toUrl(),
          ];
        }
      } else {
        $items[$uid]['ilr_employee_persona'] = [
          '#type' => 'markup',
          '#markup' => Markup::create('No ILR employee persona was found.')
        ];
      }

      $form['accounts'][$uid] = [
        '#type' => 'hidden',
        '#value' => $uid,
      ];
    }

    $form['account']['info'] = [
      '#theme' => 'item_list',
      '#list_type' => 'ol',
      '#items' => $items,
    ];

    $form['operation'] = ['#type' => 'hidden', '#value' => 'cancel'];

    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * Get roles for an account.
   *
   * @return array
   *   The roles for this account.
   */
  protected function getRoles($account) {
    $all_roles = $account->getRoles();
    $keep_roles = ['authenticated'];
    $roles_to_remove = array_diff($all_roles, $keep_roles);
    $removed_roles = [];
    foreach ($roles_to_remove as $role_id) {
      $removed_roles[] = $role_id;
    }
    return $removed_roles;
  }

  /**
   * Get collections owned by an account.
   *
   * @return array
   *   An array of collection entities.
   */
  protected function getUserCollections($account) {
    $user_collections = $this->entityTypeManager->getStorage('collection')->loadByProperties([
      'user_id' => [$account->id()],
    ]);
    return $user_collections;
  }

  /**
   * Get employee persona for an account.
   *
   * @return \Drupal\person\PersonaInterface
   *    The employee persona.
   */
  protected function getEmployeePersona($account) {
    $netid = \Drupal::service('externalauth.authmap')->get($account->id(), 'samlauth');

    if (!$netid) {
      return FALSE;
    }

    $ilr_employee_persona = $this->entityTypeManager->getStorage('persona')->loadByProperties([
      'type' => 'ilr_employee',
      'field_netid' => $netid,
    ]);

    if (!empty($ilr_employee_persona)) {
      $persona = reset($ilr_employee_persona);
      return $persona;
    }
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $current_user_id = $this->currentUser()->id();

    // Clear out the accounts from the temp store.
    $this->tempStoreFactory->get('ilr_user_kissoff_action')->delete($current_user_id);

    if ($form_state->getValue('confirm')) {
      foreach ($form_state->getValue('accounts') as $uid => $value) {
        $account = $this->userStorage->load($uid);

        if ($roles = $this->getRoles($account)) {
          foreach ($roles as $role_id) {
            $account->removeRole($role_id);
          }
          $account->save();
          $this->messenger()->addMessage($this->t('Roles were removed from @account.', [
            '@account' => $account->label(),
          ]));
        }

        if ($user_collections = $this->getUserCollections($account)) {
          foreach ($user_collections as $user_collection) {
            $owners = $user_collection->get('user_id')->getValue();
            $account_id = $account->id();

            $new_owners = array_filter($owners, function ($item) use ($account_id) {
              return $item['target_id'] != $account_id;
            });

            $user_collection->set('user_id', $new_owners);

            $user_collection->save();

            $this->messenger()->addMessage($this->t('Collections were removed from @account.', [
            '@account' => $account->label(),
          ]));
          }
        }

        if ($ilr_employee_persona = $this->getEmployeePersona($account)) {
          $ilr_employee_persona->status = 0;
          $ilr_employee_persona->save();
          $this->messenger()->addMessage($this->t('Profile for @account was unpublished.', [
            '@account' => $account->label(),
          ]));
        }
      }
    }
    $form_state->setRedirect('entity.user.collection');
  }

}
