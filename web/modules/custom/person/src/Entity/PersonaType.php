<?php

namespace Drupal\person\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\person\PersonaTypeInterface;

/**
 * Defines the Persona type entity.
 *
 * @ConfigEntityType(
 *   id = "persona_type",
 *   label = @Translation("Persona type"),
 *   label_collection = @Translation("Persona types"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\person\PersonaTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\person\Form\PersonaTypeForm",
 *       "edit" = "Drupal\person\Form\PersonaTypeForm",
 *       "delete" = "Drupal\person\Form\PersonaTypeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "persona_type",
 *   admin_permission = "administer site configuration",
 *   bundle_of = "persona",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/persona/add",
 *     "edit-form" = "/admin/structure/persona/{persona_type}",
 *     "delete-form" = "/admin/structure/persona/{persona_type}/delete",
 *     "collection" = "/admin/structure/persona"
 *   }
 * )
 */

class PersonaType extends ConfigEntityBundleBase implements PersonaTypeInterface {

  /**
   * The Persona type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Persona type label.
   *
   * @var string
   */
  protected $label;

}
