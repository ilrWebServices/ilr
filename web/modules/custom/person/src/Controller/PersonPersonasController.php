<?php

namespace Drupal\person\Controller;

use Drupal\collection\ContentEntityCollectionListBuilder;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\person\PersonaManager;
use Drupal\person\PersonInterface;
use Drupal\person\PersonPersonasListBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns a page of personas for a person.
 */
class PersonPersonasController extends ControllerBase {

  /**
   * Constructs a ContentEntityCollectionsController object.
   */
  public function __construct(
    protected PersonaManager $personaManager
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('persona.manager')
    );
  }

  public function content(PersonInterface $person) {
    $persona_definition = $this->entityTypeManager()->getDefinition('persona');
    $personas = $this->personaManager->getPersonas($person);
    $list_builder = new PersonPersonasListBuilder($personas, $persona_definition);
    $build = [];

    $build['table'] = [
      '#theme' => 'container__person_personas',
      '#children' => [
        'list' => $list_builder->render(),
      ],
      '#attributes' => [
        'class' => ['person-personas'],
      ],
      '#entity' => $person,
    ];

    return $build;
  }

  public function title(RouteMatchInterface $route_match, PersonInterface $person) {
    return $this->t('Personas for @name', ['@name' => $person->label()]);
  }

}
