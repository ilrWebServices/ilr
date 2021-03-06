<?php

namespace Drupal\person\Form;

use Drupal\Core\Form\ConfigFormBase;

/**
 * Configure user settings for this site.
 *
 * @internal
 */
class PersonSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'person_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [];
  }

}
