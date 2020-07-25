<?php

namespace Drupal\menu_graft\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Represents a menu link for another, single grafted menu link.
 */
class MenuGraftMenuLink extends MenuLinkBase implements ContainerFactoryPluginInterface {

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new MenuGraftMenuLink.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MessengerInterface $messenger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return (string) $this->pluginDefinition['title'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return (string) $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function isDeletable() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteLink() {
  }

  /**
   * {@inheritdoc}
   */
  public function updateLink(array $new_definition_values, $persist) {
    // Normally, we do not allow individual updates to MenuGraftMenuLinks. But
    // when \Drupal\Core\Menu\MenuLinkManagerInterface::updateDefinition() is
    // called in the helper service, we need a way to bypass that restriction.
    // This is done with a special plugin definition value that is set in the
    // helper.
    if (isset($new_definition_values['menu_graft_cascade_update'])) {
      unset($new_definition_values['menu_graft_cascade_update']);
      $this->pluginDefinition = $new_definition_values + $this->pluginDefinition;
    }
    else {
      $this->messenger->addWarning($this->t('Changes to %title are ignored because it is part of a grafted menu.', [
        '%title' => $this->getTitle(),
      ]));
    }

    return $this->getPluginDefinition();
  }

}
