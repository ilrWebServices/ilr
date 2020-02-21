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
    $options['query'] = \Drupal::request()->query->all();

    return new RedirectResponse(Url::fromRoute($route_name, $route_parameters, $options)->toString(), $status);
  }

  /**
   * {@inheritdoc}
   *
   * Pass along the `person` query parameter to the links on the add persona
   * page.
   */
  public function addPage($entity_type_id) {
    $build = parent::addPage($entity_type_id);

    if ($build instanceof RedirectResponse) {
      return $build;
    }

    $options = [];
    $options['query'] = \Drupal::request()->query->all();

    foreach ($build['#bundles'] as $key => $bundle) {
      $persona_add_form_url = Url::fromRoute('entity.persona.add_form', [
        'persona_type' => $key
      ], $options);

      $build['#bundles'][$key]['add_link']->setUrl($persona_add_form_url);
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function title(RouteMatchInterface $route_match, EntityInterface $_entity = NULL) {
    if ($entity = $this->doGetEntity($route_match, $_entity)) {
      return $entity->getDisplayName();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function editTitle(RouteMatchInterface $route_match, EntityInterface $_entity = NULL) {
    if ($entity = $this->doGetEntity($route_match, $_entity)) {
      return $this->t('Edit %label @bundle persona', [
        '%label' => $entity->label(),
        '@bundle' => $entity->bundle(),
      ]);
    }
  }

}
