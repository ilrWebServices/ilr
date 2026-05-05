<?php

namespace Drupal\ilr_employee_data\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\person\PersonaInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 *
 */
class UserProfileEditController extends ControllerBase {

  /**
   *
   */
  public function view(PersonaInterface $persona, Request $request): RedirectResponse {
    if ($this->currentUser()->isAuthenticated()) {
      return $this->redirect('entity.persona.edit_form', ['persona' => $persona->id()]);
    }

    return $this->redirect('samlauth.saml_controller_login', [], ['query' => ['destination' => $request->getRequestUri()]]);
  }

}
