<?php

namespace Drupal\person\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;

/**
 * Provides a Person entity deletion form.
 */
class PersonDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This will also delete associated personas and cannot be undone.');
  }

}
