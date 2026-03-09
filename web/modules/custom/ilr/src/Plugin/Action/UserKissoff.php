<?php

namespace Drupal\ilr\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\externalauth\AuthmapInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Performs ILR-related cleanup of a user account.
 */
#[Action(
  id: 'ilr_user_kissoff_action',
  label: new TranslatableMarkup('Remove the selected ILR user and clean up their account.'),
  type: 'user'
)]
class UserKissoff extends ActionBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Authmap service.
   *
   * @var \Drupal\externalauth\AuthmapInterface
   */
  protected $authmap;

  /**
   * Constructs a UserKissoff object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\externalauth\AuthmapInterface $authmap
   *   The authmap service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, AuthmapInterface $authmap) {
    $this->entityTypeManager = $entity_type_manager;
    $this->authmap = $authmap;

    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('externalauth.authmap')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute($account = NULL) {
    $netid = $this->authmap->get($account->id(), 'samlauth');

    if (!$netid) {
      $this->messenger()->addMessage('The referenced account does not appear to have a netID');
    }

    if ($netid) {
      // Remove all roles from the user account.
      $all_roles = $account->getRoles();
      $keep_roles = ['authenticated'];
      $roles_to_remove = array_diff($all_roles, $keep_roles);
      $removed_roles = [];
      foreach ($roles_to_remove as $role_id) {
        $account->removeRole($role_id);
        $removed_roles[] = $role_id;
      }

      if (!empty($removed_roles)) {
        $this->messenger()->addMessage('The following roles were removed: ' . implode(',', $removed_roles));
      }

      // Remove the user from all Collections.
      $user_collections = $this->entityTypeManager->getStorage('collection')->loadByProperties([
        'user_id' => [$account->id()],
      ]);
      $removed_collections = [];

      foreach ($user_collections as $user_collection) {
        $owners = $user_collection->get('user_id')->getValue();
        $account_id = $account->id();

        $new_owners = array_filter($owners, function ($item) use ($account_id) {
          return $item['target_id'] != $account_id;
        });

        $user_collection->set('user_id', $new_owners);

        $user_collection->save();
        $removed_collections[$user_collection->id()] = $user_collection->label();
      }

      if (!empty($removed_collections)) {
        $this->messenger()->addMessage('The user was removed from the following collections: ' . implode(', ', $removed_collections));
      }

      // Unpublish their employee persona.
      $ilr_employee_persona = $this->entityTypeManager->getStorage('persona')->loadByProperties([
        'type' => 'ilr_employee',
        'field_netid' => $netid,
      ]);

      if (!empty($ilr_employee_persona)) {
        $persona = reset($ilr_employee_persona);
        $persona->status = 0;
        $persona->save();

        $this->messenger()->addMessage("The user's ILR employee persona was unpublished.");
      }

      $account->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\user\UserInterface $object */
    $access = $object->status->access('edit', $account, TRUE)
      ->andIf($object->access('update', $account, TRUE));

    return $return_as_object ? $access : $access->isAllowed();
  }

}
