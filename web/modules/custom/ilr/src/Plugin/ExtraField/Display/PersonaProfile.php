<?php

namespace Drupal\ilr\Plugin\ExtraField\Display;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayBase;
use Drupal\person\PersonaManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Canonical home field Display.
 *
 * @ExtraFieldDisplay(
 *   id = "profile",
 *   label = @Translation("Profile"),
 *   bundles = {
 *     "persona.*",
 *   },
 *   visible = true
 * )
 */
class PersonaProfile extends ExtraFieldDisplayBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The persona manager service.
   *
   * @var \Drupal\person\PersonaManager
   */
  protected $personaManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->personaManager = $container->get('persona.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function view(ContentEntityInterface $persona) {
    $build = [];

    if ($profile = $this->personaManager->getPersonaProfile($persona)) {
      $build = [
        '#url' => $profile->toUrl(),
        '#title' => $this->t('View full profile'),
        '#type' => 'link',
        '#prefix' => '<div class="profile-link">',
        '#suffix' => '</div>',
      ];
    }

    return $build;
  }

}
