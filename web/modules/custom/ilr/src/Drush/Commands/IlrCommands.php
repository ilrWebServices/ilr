<?php

namespace Drupal\ilr\Drush\Commands;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Drush commandfile for ILR custom drush commands.
 */
final class IlrCommands extends DrushCommands {

  /**
   * Constructs an IlrCommands object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Add a publications, awards, and activities paragraphs to 'Faculty' ilr_employee personas.
   */
  #[CLI\Command(name: 'ilr:faculty-remote-data')]
  #[CLI\Usage(name: 'ilr:faculty-remote-data', description: 'Add remote data paragraphs (pubs, awards, activities) to "Faculty" ilr_employee personas if they are missing.')]
  public function facultyPubsCommand() {
    $ilr_employee_personas = $this->entityTypeManager->getStorage('persona')->loadByProperties([
      'type' => 'ilr_employee',
      'field_employee_role.entity.name' => 'Faculty',
    ]);

    /** @var \Drupal\person\PersonaInterface $persona */
    foreach ($ilr_employee_personas as $persona) {
      $remote_paragraph_types = [
        'publications' => 'remote_publications',
        'honors_and_awards' => 'remote_awards',
        'professional_activities' => 'remote_activities',
      ];

      /** @var \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList $paragraphs */
      $paragraphs = $persona->field_components;

      foreach ($paragraphs->referencedEntities() as $paragraph) {
        if (isset($remote_paragraph_types[$paragraph->bundle()])) {
          unset($remote_paragraph_types[$paragraph->bundle()]);
        }
      }

      foreach ($remote_paragraph_types as $remote_paragraph_type => $remote_paragraph_setting_name) {
        /** @var \Drupal\paragraphs\ParagraphInterface $new_paragraph */
        $new_paragraph = $this->entityTypeManager->getStorage('paragraph')->create([
          'type' => $remote_paragraph_type,
        ]);

        if ($persona->hasField('field_netid') && $persona->get('field_netid')) {
          $settings = [
            $remote_paragraph_setting_name => ['netid' => $persona->field_netid->value],
          ];

          $new_paragraph->setAllBehaviorSettings($settings);
          $new_paragraph->save();
        }

        $persona->field_components->appendItem($new_paragraph);
        $persona->save();
      }
    }
  }

  /**
   * Add legacy redirects from a CSV source to ilr_employee personas.
   *
   * We don't do this with a migration (although we could using the
   * entity_lookup process plugin) because we also want to do some existing path
   * matching and it's just too clunky with migrate.
   */
  #[CLI\Command(name: 'ilr:legacy-person-path')]
  #[CLI\Usage(name: 'ilr:legacy-person-path', description: 'Add redirects for legacy people paths to ilr_employee personas if they are different from the new path aliases.')]
  public function ilrLegacyPeoplePathsCommand() {
    $data_url = 'https://raw.githubusercontent.com/ilrWebServices/people-data/refs/heads/main/d7_people_paths.csv';
    $file = new \SplFileObject($data_url);

    while (!$file->eof()) {
      $row = $file->fgetcsv();
      list($netid, $path) = $row;

      if ($netid === 'netid') {
        continue;
      }

      // Is there an existing path alias?
      $existing_path_alias = $this->entityTypeManager->getStorage('path_alias')->loadByProperties([ 'alias' => '/' . $path ]);

      if (!empty($existing_path_alias)) {
        continue;
      }

      // Is there an existing redirect?
      $existing_redirect = $this->entityTypeManager->getStorage('redirect')->loadByProperties([ 'redirect_source__path' => $path ]);

      if (!empty($existing_redirect)) {
        continue;
      }

      $ilr_employee_persona = $this->entityTypeManager->getStorage('persona')->loadByProperties([
        'type' => 'ilr_employee',
        'field_netid' => $netid,
      ]);

      if (empty($ilr_employee_persona)) {
        continue;
      }

      $ilr_employee_persona = reset($ilr_employee_persona);

      $redirect = $this->entityTypeManager->getStorage('redirect')->create([
        'redirect_source' => $path,
        'redirect_redirect' => 'internal:/persona/' . $ilr_employee_persona->id(),
        'status_code' => 301,
      ]);

      $redirect->save();
    }
  }

  /**
   * Dedupe person entities by merging persons to the person referenced by their
   * ilr_employee personas.
   */
  #[CLI\Command(name: 'ilr:person-dedupe')]
  #[CLI\Argument(name: 'names', description: 'A comma separated list of person display names.')]
  #[CLI\Usage(name: 'ilr:person-dedupe', description: 'Dedupe person entities by merging persons to the person referenced by their ilr_employee personas.')]
  public function ilrPersonDedupe(string $names) {
    $person_storage = $this->entityTypeManager->getStorage('person');
    $persona_storage = $this->entityTypeManager->getStorage('persona');
    $names = explode(',', $names);

    // Get all of the personas for all of the person arguments.
    $personas = $persona_storage->loadByProperties([
      'person.entity.display_name' => $names,
    ]);

    if (empty($personas)) {
      throw new \Exception(dt('No personas found.'));
    }

    // See if one of the personas is of the ilr_employee type.
    $ilr_employee_persona = false;

    foreach ($personas as $persona) {
      if ($persona->bundle() === 'ilr_employee') {
        $ilr_employee_persona = $persona;
        break;
      }
    }

    if ($ilr_employee_persona) {
      $keeper_person = $ilr_employee_persona->person->entity;
      $this->output()->writeln('Person to keep: ' . $keeper_person->getDisplayName() . ' (' . $keeper_person->id() . ').');
    }
    else {
      throw new \Exception(dt('No ILR Employee persona found.'));
    }

    $persons_to_delete = [];

    // Assign the person of the ilr_employee persona to all of the other personas.
    foreach ($personas as $persona) {
      if ($persona->person->target_id === $keeper_person->id()) {
        continue;
      }

      $persons_to_delete[$persona->person->entity->id()] = $persona->person->entity;
      $persona->set('person', $keeper_person->id());
      $persona->save();
    }

    if ($persons_to_delete) {
      // Delete all of the person entities that aren't the ilr_employee persona person.
      $person_storage->delete($persons_to_delete);
      $this->output()->writeln('Removed person entities: ' . implode(', ', array_keys($persons_to_delete)));
    }
    else {
      $this->output()->writeln('No duplicate person entities found.');
    }
  }

  /**
   * Load the 'host entity' and output its url. This is useful for 'nested' paragraphs.
   */
  #[CLI\Command(name: 'ilr:paragraphs-host', aliases: ['ph', 'phe', 'parahost'])]
  #[CLI\Argument(name: 'pid', description: 'The paragraphs item id.')]
  #[CLI\Option(name: 'browser', description: 'Open the URL in the default browser. Use --no-browser to avoid opening a browser.')]
  #[CLI\Usage(name: 'ilr:paragraphs-host 1234', description: 'Output the url for the host entity for paragraph 1234')]
  public function pheCommand($pid, $options = ['browser' => true]) {
    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');

    if (!$paragraph = $paragraph_storage->load($pid)) {
      throw new \Exception(dt('Unable to load paragraph by id: !pid', ['!pid' => $pid]));
    }

    if ($host_entity = $this->getNonParagraphParentReferencingEntity($paragraph)) {
      $link = $host_entity->toUrl('canonical', ['absolute' => TRUE])->toString();
      $this->startBrowser($link, 0, null, $options['browser']);
      return $this->output()->writeln('Found on ' . $link);
    }
    else {
      throw new \Exception(dt('Host entity not found.'));
    }
  }

  /**
   * Returns a non-paragraph referencing entity for a given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that might be a paragraph.
   *
   * @return Drupal\Core\Entity\EntityInterface $entity
   *   The entity, or the root parent entity of nested paragraphs.
   */
  protected function getNonParagraphParentReferencingEntity(EntityInterface $entity) {
    if ($entity instanceof ParagraphInterface) {
      do {
        $entity = $entity->getParentEntity();
      } while ($entity instanceof ParagraphInterface);
    }
    return $entity;
  }

}
