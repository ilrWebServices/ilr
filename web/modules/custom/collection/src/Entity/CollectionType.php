<?php

namespace Drupal\collection\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Collection type entity.
 *
 * @ConfigEntityType(
 *   id = "collection_type",
 *   label = @Translation("Collection type"),
 *   label_collection = @Translation("Collection types"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\collection\CollectionTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\collection\Form\CollectionTypeForm",
 *       "edit" = "Drupal\collection\Form\CollectionTypeForm",
 *       "delete" = "Drupal\collection\Form\CollectionTypeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "collection_type",
 *   admin_permission = "administer site configuration",
 *   bundle_of = "collection",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/collection/add",
 *     "edit-form" = "/admin/structure/collection/{collection_type}",
 *     "delete-form" = "/admin/structure/collection/{collection_type}/delete",
 *     "collection" = "/admin/structure/collection"
 *   }
 * )
 */
class CollectionType extends ConfigEntityBundleBase implements CollectionTypeInterface {

  /**
   * The Collection type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Collection type label.
   *
   * @var string
   */
  protected $label;

  /**
   * The Collection type allow collection item types.
   *
   * @var array
   */
  protected $allowed_collection_item_types = [];

  /**
   * {@inheritdoc}
   */
  public function getAllowedCollectionItemTypes() {
    return $this->allowed_collection_item_types;
  }

}
