<?php

namespace Drupal\person;

use Drupal\Core\Entity\BundlePermissionHandlerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\person\Entity\PersonaType;

/**
 * Provides dynamic permissions for personas of different types.
 */
class PersonPermissions {

  use BundlePermissionHandlerTrait;
  use StringTranslationTrait;

  /**
   * Returns an array of persona type permissions.
   *
   * @return array
   *   The persona type permissions.
   *   @see \Drupal\user\PermissionHandlerInterface::getPermissions()
   */
  public function personaTypePermissions() {
    return $this->generatePermissions(PersonaType::loadMultiple(), [$this, 'buildPermissions']);
  }

  /**
   * Returns a list of persona permissions for a given persona type.
   *
   * @param \Drupal\node\Entity\PersonaType $type
   *   The persona type.
   *
   * @return array
   *   An associative array of permission names and descriptions.
   */
  protected function buildPermissions(PersonaType $type) {
    $type_id = $type->id();
    $type_params = ['%type_name' => $type->label()];

    return [
      "create $type_id persona" => [
        'title' => $this->t('%type_name: Create new persona', $type_params),
      ],
      // "edit own $type_id persona" => [
      //   'title' => $this->t('%type_name: Edit own persona', $type_params),
      //   'description' => $this->t('Note that anonymous users with this permission are able to edit any persona created by any anonymous user.'),
      // ],
      "edit any $type_id persona" => [
        'title' => $this->t('%type_name: Edit any persona', $type_params),
      ],
      // "delete own $type_id persona" => [
      //   'title' => $this->t('%type_name: Delete own persona', $type_params),
      //   'description' => $this->t('Note that anonymous users with this permission are able to delete any persona created by any anonymous user.'),
      // ],
      "delete any $type_id persona" => [
        'title' => $this->t('%type_name: Delete any persona', $type_params),
      ],
      // "view $type_id revisions" => [
      //   'title' => $this->t('%type_name: View revisions', $type_params),
      //   'description' => t('To view a revision, you also need permission to view the persona item.'),
      // ],
      // "revert $type_id revisions" => [
      //   'title' => $this->t('%type_name: Revert revisions', $type_params),
      //   'description' => t('To revert a revision, you also need permission to edit the persona item.'),
      // ],
      // "delete $type_id revisions" => [
      //   'title' => $this->t('%type_name: Delete revisions', $type_params),
      //   'description' => $this->t('To delete a revision, you also need permission to delete the persona item.'),
      // ],
    ];
  }

}
