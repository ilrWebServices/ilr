<?php

use Drupal\Core\Field\BaseFieldDefinition;

/**
 * @file
 * Post update functions for the Person module.
 */

 /**
 * Add the default base field to personas.
 */
function person_post_update_persona_default(&$sandbox) {
  $definition_update_manager = \Drupal::entityDefinitionUpdateManager();
  $entity_type = $definition_update_manager->getEntityType('persona');

  /** @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $last_installed_schema_repository */
  $last_installed_schema_repository = \Drupal::service('entity.last_installed_schema.repository');
  $field_storage_definitions = $last_installed_schema_repository->getLastInstalledFieldStorageDefinitions('persona');

  $field_storage_definitions['default'] = BaseFieldDefinition::create('boolean')
    ->setName('default')
    ->setLabel(t('Default persona'))
    ->setDescription(t('A flag indicating whether this is the default persona.'))
    ->setDefaultValue(FALSE)
    ->setInitialValue(FALSE)
    ->setRevisionable(TRUE);

  $definition_update_manager->installFieldStorageDefinition('default', $entity_type->id(), 'persona', $field_storage_definitions['default']);

  // Update the entity type and the database schema, including data migration.
  $definition_update_manager->updateFieldableEntityType($entity_type, $field_storage_definitions, $sandbox);
}
