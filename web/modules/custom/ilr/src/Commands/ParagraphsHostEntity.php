<?php

namespace Drupal\ilr\Commands;

use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drupal\Core\Entity\EntityInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

/**
 * A Drush commandfile to load the host entity for a paragraph.
 */
class ParagraphsHostEntity extends DrushCommands {

  /**
   * Load the 'host entity' and output its url. This is useful for 'nested' paragraphs.
   *
   * @param string $pid
   *   The paragaphs item id.
   *
   * @command paragraphs:host
   * @aliases phe,paragraphs-host
   * @option pid The paragraphs item id.
   * @usage paragraphs:host --pid=1234
   *   Output the url for the host entity for paragraph 1234.
   */
  public function host($pid, $options = ['go' => FALSE, 'browser' => TRUE]) {
    if (!Drush::bootstrapManager()->doBootstrap(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
      throw new \Exception(dt('Unable to bootstrap Drupal.'));
    }

    if (!$paragraph = Paragraph::load($pid)) {
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
