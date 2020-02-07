<?php

namespace Drupal\person\Controller;

use Drupal\Core\Entity\Controller\EntityController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Entity\EntityInterface;

class PersonaController extends EntityController {

  /**
   * {@inheritdoc}
   */
  protected function redirect($route_name, array $route_parameters = [], array $options = [], $status = 302) {
    $options['absolute'] = TRUE;
    $route_parameters['person'] = \Drupal::routeMatch()->getRawParameter('person');
    return new RedirectResponse(Url::fromRoute($route_name, $route_parameters, $options)->toString(), $status);
  }

  /**
   * {@inheritdoc}
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
