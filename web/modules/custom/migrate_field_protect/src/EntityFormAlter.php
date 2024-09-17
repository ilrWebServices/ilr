<?php

namespace Drupal\migrate_field_protect;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\migrate\Plugin\migrate\destination\EntityContentBase;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;

/**
 * A service to alter a content entity form.
 */
class EntityFormAlter {

  use StringTranslationTrait;

  /**
   * EntityFormAlter constructor.
   */
  public function __construct(
    protected AccountProxy $account,
    protected ConfigFactoryInterface $configFactory,
    protected MigrationPluginManagerInterface $migrationPluginManager
  ) {}

  /**
   * Alter a content entity edit form.
   *
   * @see migrate_field_protect_form_alter().
   */
  public function alterForm(array &$form, FormStateInterface $form_state, string $form_id) {
    $form_object = $form_state->getFormObject();

    if (!$form_object instanceof ContentEntityForm || $form_object instanceof ContentEntityDeleteForm) {
      return;
    }

    $entity = $form_object->getEntity();

    if ($entity->isNew()) {
      return;
    }

    $id_key = $entity->getEntityType()->getKey('id');

    // Get scheduled migrations, if any.
    $migrate_scheduler_config = $this->configFactory->get('migrate_scheduler')->get('migrations');

    if (empty($migrate_scheduler_config)) {
      return;
    }

    $recurring_migrations = array_keys($migrate_scheduler_config);

    foreach ($recurring_migrations as $migration_id) {
      /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
      $migration = $this->migrationPluginManager->createInstance($migration_id);

      $destination = clone $migration->getDestinationPlugin();

      // Check if the migration imports entities.
      if (!$destination instanceof EntityContentBase) {
        continue;
      }

      $destination_config = $migration->getDestinationConfiguration();

      // Check the migration destination config to see if it imports the same
      // kind of entity type.
      // Another way to do it: dump(preg_match('/' . $entity->getEntityTypeId() . '$/', $destination_config_plugin));
      if (!str_ends_with($destination_config['plugin'], $entity->getEntityTypeId())) {
        continue;
      }

      $id_map = $migration->getIdMap();

      // Check for a mapping row for this entity.
      $map_row = $id_map->getRowByDestination([
        $id_key => $entity->id(),
      ]);

      // If a mapping row is not found, this entity was not migrated.
      if (empty($map_row)) {
        continue;
      }

      $process = $migration->getProcess();

      $form['imported_info'] = [
        '#type' => 'details',
        '#title' => t('Imported info'),
        '#group' => 'advanced',
        '#weight' => -10,
        '#optional' => TRUE,
        '#open' => TRUE,
      ];

      foreach (array_keys($process) as $potential_field_name) {
        // Some process items are in the form field_whatever/value or similar.
        // We only want that first part.
        $potential_field_name = preg_replace('|(.*)/.*|', '$1', $potential_field_name);

        if (isset($form[$potential_field_name])) {
          // If so, disable any fields mapped in the migration.
          $form[$potential_field_name]['#disabled'] = TRUE;

          if (($form[$potential_field_name]['#group'] ?? '') !== 'meta') {
            $form[$potential_field_name]['#group'] = 'imported_info';
          }
        }
      }
    }
  }

}
