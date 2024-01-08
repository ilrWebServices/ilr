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
   * Load the 'host entity' and output its url. This is useful for 'nested' paragraphs.
   */
  #[CLI\Command(name: 'ilr:paragraphs-host', aliases: ['ph', 'phe', 'parahost'])]
  #[CLI\Argument(name: 'pid', description: 'The paragraphs item id.')]
  #[CLI\Usage(name: 'ilr:paragraphs-host 1234', description: 'Output the url for the host entity for paragraph 1234')]
  public function pheCommand($pid, $options = []) {
    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');

    if (!$paragraph = $paragraph_storage->load($pid)) {
      throw new \Exception(dt('Unable to load paragraph by id: !pid', ['!pid' => $pid]));
    }

    if ($host_entity = $this->getNonParagraphParentReferencingEntity($paragraph)) {
      $link = $host_entity->toUrl('canonical', ['absolute' => TRUE])->toString();
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
