<?php

namespace Drupal\person\Controller;

use Drupal\Core\Entity\Controller\EntityController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Entity\EntityInterface;

class PersonaController extends EntityController {

  /**
   * Returns a redirect response object for the specified route.
   *
   * This is overridden from EntityController because we need to ensure that the
   * `person` parameter is included in the redirect response. This method is
   * only called from the parent of $this->addPage.
   *
   * @param string $route_name The name of the route to which to redirect.
   * @param array $route_parameters (optional) Parameters for the route.
   * @param array $options (optional) An associative array of additional
   *   options.
   * @param int $status (optional) The HTTP redirect status code for the
   *   redirect. The default is 302 Found.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse A redirect
   *   response object that may be returned by the controller.
   */
  protected function redirect($route_name, array $route_parameters = [], array $options = [], $status = 302) {
    $options['absolute'] = TRUE;
    $route_parameters['person'] = \Drupal::routeMatch()->getRawParameter('person');
    return new RedirectResponse(Url::fromRoute($route_name, $route_parameters, $options)->toString(), $status);
  }

  /**
   * Displays add links for the available persona bundles.
   *
   * Redirects to the add form if there's only one bundle available.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|array
   *   If there's only one available bundle, a redirect response.
   *   Otherwise, a render array with the add links for each bundle.
   */
  public function addPage($entity_type_id) {
    $build = parent::addPage($entity_type_id);

    if ($build instanceof RedirectResponse) {
      return $build;
    }

    foreach ($build['#bundles'] as $key => $bundle) {
      $persona_add_url = Url::fromRoute('entity.persona.add_form', [
        'person' => \Drupal::routeMatch()->getRawParameter('person'),
        'persona_type' => $key
      ]);

      $build['#bundles'][$key]['add_link']->setUrl($persona_add_url);
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function collectionTitle(RouteMatchInterface $route_match, EntityInterface $_entity = NULL) {
    // In our case, $_entity will be the `person`, since the `persona` routes
    // include it as an additional parameter.
    if ($entity = $this->doGetEntity($route_match, $_entity)) {
      return $this->t('Personas for @label', [
        '@label' => $entity->label(),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addBundleTitle(RouteMatchInterface $route_match, $entity_type_id, $bundle_parameter) {
    $person = $route_match->getParameter('person');
    $persona_type = $route_match->getParameter($bundle_parameter);

    return $this->t('Add @persona_type persona for %person', [
      '@persona_type' => $persona_type->label(),
      '%person' => $person->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function editTitle(RouteMatchInterface $route_match, EntityInterface $_entity = NULL) {
    // Don't use $this->doGetEntity($route_match, $_entity) here, because it
    // will get the `person` entity that is included in all `persona` routes.
    $persona = $route_match->getParameter('persona');

    return $this->t('Edit %label @bundle persona', [
      '%label' => $persona->label(),
      '@bundle' => $persona->bundle(),
    ]);
  }

}
