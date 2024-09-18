<?php

namespace Drupal\ilr\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\externalauth\Authmap;
use Drupal\externalauth\AuthmapInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a profile edit link block.
 */
#[Block(
  id: "ilr_profile_edit_link",
  admin_label: new TranslatableMarkup("Profile edit link"),
  category: new TranslatableMarkup("ILR")
)]
class ProfileEditLinkBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $account,
    protected AuthmapInterface $authmap,
  ) {
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
      $container->get('current_user'),
      $container->get('externalauth.authmap')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @todo Find (or create) a cache context that only considers the first part of
   * the url.path (e.g. only `foo` from `foo/bar/baz`). See
   * PathParentCacheContext.
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['url.path']);
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build = [];

    // Check for an ilr_employee persona with the same netid as the current
    // user's authmap key.
    $netid = $this->authmap->get($this->account->id(), 'samlauth');

    if (empty($netid)) {
      return $build;
    }

    /** @var \Drupal\person\Entity\Persona $profile_persona */
    $profile_persona = $this->entityTypeManager->getStorage('persona')->loadByProperties([
      'type' => 'ilr_employee',
      'field_netid' => $netid,
    ]);

    if (empty($profile_persona)) {
      return $build;
    }

    $profile_persona = reset($profile_persona);

    $build['content'] = [
      '#title' => $this->t('Edit your employee profile'),
      '#type' => 'link',
      '#url' => $profile_persona->toUrl('edit-form'),
      '#attributes' => [
        'class' => ['profile-button'],
      ],
    ];

    return $build;
  }

}
