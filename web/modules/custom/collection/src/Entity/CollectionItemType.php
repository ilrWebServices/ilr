<?php

namespace Drupal\collection\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Collection item type entity.
 *
 * @ConfigEntityType(
 *   id = "collection_item_type",
 *   label = @Translation("Collection item type"),
 *   label_collection = @Translation("Collection item types"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\collection\CollectionItemTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\collection\Form\CollectionItemTypeForm",
 *       "edit" = "Drupal\collection\Form\CollectionItemTypeForm",
 *       "delete" = "Drupal\collection\Form\CollectionItemTypeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "collection_item_type",
 *   admin_permission = "administer site configuration",
 *   bundle_of = "collection_item",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/collection_item/add",
 *     "edit-form" = "/admin/structure/collection_item/{collection_item_type}",
 *     "delete-form" = "/admin/structure/collection_item/{collection_item_type}/delete",
 *     "collection" = "/admin/structure/collection_item"
 *   }
 * )
 */
class CollectionItemType extends ConfigEntityBundleBase implements CollectionItemTypeInterface {

  /**
   * The Collection item type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Collection item type label.
   *
   * @var string
   */
  protected $label;
}
