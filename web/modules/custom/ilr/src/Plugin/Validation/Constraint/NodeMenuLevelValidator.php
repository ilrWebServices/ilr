<?php

namespace Drupal\ilr\Plugin\Validation\Constraint;

use Drupal\menu_link_content\Entity\MenuLinkContent;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the NodeMenuLevel constraint.
 *
 * Note that we have access to the menu property on nodes thanks to the menu_ui
 * module.
 *
 * @see menu_ui_node_builder().
 */
class NodeMenuLevelValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    if ($menu_form_values = $entity->menu) {
      if (!$menu_form_values['enabled']) {
        return;
      }

      list($menu_name, $parent) = explode(':', $menu_form_values['menu_parent'], 2);

      if ($menu_name === 'main' && empty($parent)) {
        if (!empty($menu_form_values['entity_id'])) {
          // Check if it's already at the root of the main menu. This allows
          // current root items to pass validation, but catches the rest.
          $existing_menu_link = MenuLinkContent::load($menu_form_values['entity_id']);

          if (empty($existing_menu_link->getParentId())) {
            return;
          }
        }

        $this->context->buildViolation($constraint->message)
          ->atPath('menu.menu_parent')
          ->addViolation();
      }
    }
  }

}
