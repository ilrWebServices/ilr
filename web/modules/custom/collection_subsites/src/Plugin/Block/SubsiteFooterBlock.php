<?php

namespace Drupal\collection_subsites\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a 'SubsiteFooterBlock' block.
 */
#[Block(
  id: "subsite_footer_block",
  admin_label: new TranslatableMarkup("Subsite footer")
)]
class SubsiteFooterBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected array $pathEntities,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected MenuLinkTreeInterface $menuTree,
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
      $container->get('path_alias.entities')->getPathAliasEntities(),
      $container->get('entity_type.manager'),
      $container->get('menu.link_tree')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account) {
    if ($this->getSubsiteFromPath()) {
      return AccessResult::allowed();
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $subsite_collection = $this->getSubsiteFromPath();

    $build = [];
    $build['#theme'] = 'subsite_footer_block';
    $build['#subsite_path'] = $subsite_collection->toUrl();
    $build['#cache']['contexts'] = ['url.path'];


    // If the collection has a logo, use it.
     if ($subsite_collection->hasField('logo') && !$subsite_collection->logo->isEmpty()) {
      $build['#subsite_logo'] = [
        '#theme' => 'image',
        '#uri' => $subsite_collection->logo->first()->entity->field_media_svg->entity->getFileUri(),
        '#style_name' => 'media_library',
        '#alt' => $this->t('@collection logo', ['@collection' => $subsite_collection->label()]),
      ];
    }

    if (!$subsite_collection->address->isEmpty()) {
      $build['#subsite_address'] =  $subsite_collection->get('address')->view([
        'type' => 'address_default',
        'label' => 'hidden', // or 'above', 'inline'
      ]);
    }

    if ($subsite_collection->phone->value) {
      $build['#subsite_phone'] = $subsite_collection->get('phone')->view([
        'type' => 'telephone_link',
        'label' => 'hidden',
      ]);
    }

    if ($email = $subsite_collection->email->value) {
      $build['#subsite_email'] = [
        '#markup' => '<a href="mailto:' . $email . '" class="subsite-footer__email">' . $email . '</a>',
      ];
    }

    if (!$subsite_collection->webform->isEmpty()) {
      $build['#subsite_form'] = [
        '#type' => 'webform',
        '#webform' => $subsite_collection->webform->target_id,
      ];
    }

    // Check for a subsite menu.
    $collection_item_storage = $this->entityTypeManager->getStorage('collection_item');
    $query = $collection_item_storage->getQuery()
      ->condition('item__target_type', 'menu')
      ->condition('item__target_id', 'subsite', 'CONTAINS')
      ->condition('collection', $subsite_collection->id())
      ->accessCheck(TRUE); // Always enforce entity access

    $menu_collection_item_id = $query->execute();

    if ($menu_collection_item_id = reset($menu_collection_item_id)) {
      $menu_collection_item = $collection_item_storage->load($menu_collection_item_id);
      $menu = $menu_collection_item->item->entity;
      $parameters = $this->menuTree->getCurrentRouteMenuTreeParameters($menu->id());
      $tree = $this->menuTree->load($menu->id(), $parameters);
      $manipulators = [
        ['callable' => 'menu.default_tree_manipulators:checkAccess'],
        ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
      ];
      $tree = $this->menuTree->transform($tree, $manipulators);
      $build['#subsite_menu'] = $this->menuTree->build($tree);
    }

    return $build;
  }

  /**
   * Return the last subsite found in the path entities.
   *
   * We reverse the array since there are examples of subsites within subsites,
   * such as the Climate Jobs Initiative.
   */
  protected function getSubsiteFromPath() {
    foreach (array_reverse($this->pathEntities) as $entity) {
      if ($entity instanceof CollectionInterface) {
        $collection_type = $this->entityTypeManager->getStorage('collection_type')->load($entity->bundle());
        if ((bool) $collection_type->getThirdPartySetting('collection_subsites', 'contains_subsites')) {
          return $entity;
        }
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $cache_tags = parent::getCacheTags();

    if ($subsite_collection = $this->getSubsiteFromPath()) {
      $cache_tags = Cache::mergeTags($cache_tags, $subsite_collection->getCacheTags());
    }

    return $cache_tags;
  }

}
