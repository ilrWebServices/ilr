<?php

namespace Drupal\ilr\Entity;

use Drupal\node\NodeInterface;
use Drupal\salesforce_mapping\Entity\MappedObjectInterface;

interface ClassNodeInterface extends NodeInterface {

  /**
   * Get any class_node Salesforce mapping for this class.
   */
  public function getClassNodeSalesforceMappedObject(): MappedObjectInterface|FALSE;

}
