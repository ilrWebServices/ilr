<?php

namespace Drupal\ilr;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\externalauth\AuthmapInterface;
use Drupal\user\ToolbarLinkBuilder;

/**
 * UserToolbarLinkBuilder replaces the placeholders generated in user_toolbar().
 */
class UserToolbarLinkBuilder extends ToolbarLinkBuilder {

  /**
   * ToolbarHandler constructor.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current user.
   *
   * @param \Drupal\externalauth\AuthmapInterface $authmap
   *   The authmap service.
   */
  public function __construct(
    AccountProxyInterface $account,
    protected AuthmapInterface $authmap,
    protected EntityTypeManagerInterface $entityTypeManager
  ) {
    parent::__construct($account);
  }

  /**
   * Lazy builder callback for rendering toolbar links.
   *
   * @return array
   *   A renderable array as expected by the renderer service.
   *
   * @see \Drupal\user\ToolbarLinkBuilder::renderToolbarLinks()
   */
  public function renderToolbarLinks() {
    $links = [];
    // Check for an ilr_employee persona with the same netid as the current
    // user's authmap key.
    $netid = $this->authmap->get($this->account->id(), 'samlauth');

    if (!empty($netid)) {
      /** @var \Drupal\person\Entity\Persona $profile_persona */
      $profile_persona = $this->entityTypeManager->getStorage('persona')->loadByProperties([
        'type' => 'ilr_employee',
        'field_netid' => $netid,
      ]);

      if (!empty($profile_persona)) {
        $profile_persona = reset($profile_persona);

        $links += [
          'ilr_employee_profile' => [
            'title' => $this->t('Edit employee profile'),
            'url' => $profile_persona->toUrl('edit-form'),
            'attributes' => [
              'title' => $this->t('Edit your ILR employee profile'),
            ],
          ],
        ];
      }
    }

    $links += [
      'account' => [
        'title' => $this->t('View dashboard'),
        'url' => Url::fromRoute('user.page'),
        'attributes' => [
          'title' => $this->t('View dashboard'),
        ],
      ],
      // 'account_edit' => [
      //   'title' => $this->t('Edit account'),
      //   'url' => Url::fromRoute('entity.user.edit_form', ['user' => $this->account->id()]),
      //   'attributes' => [
      //     'title' => $this->t('Edit user account'),
      //   ],
      // ],
      'logout' => [
        'title' => $this->t('Log out'),
        'url' => Url::fromRoute('user.logout'),
      ],
    ];

    $build = [
      '#theme' => 'links__toolbar_user',
      '#links' => $links,
      '#attributes' => [
        'class' => ['toolbar-menu'],
      ],
      '#cache' => [
        'contexts' => ['user'],
      ],
    ];

    return $build;
  }

}
