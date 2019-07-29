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
 *     "add-form" = "/admin/collection/types/add",
 *     "edit-form" = "/admin/collection/types/{collection_type}",
 *     "delete-form" = "/admin/collection/types/{collection_type}/delete",
 *     "collection" = "/admin/collection/types"
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

}
