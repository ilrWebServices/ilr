<?php

declare(strict_types=1);

namespace Drupal\ilr_employee_position\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\ilr_employee_position\ILREmployeePositionInterface;

/**
 * Defines the ilr employee position entity class.
 *
 * @ContentEntityType(
 *   id = "ilr_employee_position",
 *   label = @Translation("ILR employee position"),
 *   label_collection = @Translation("ILR employee positions"),
 *   label_singular = @Translation("ILR employee position"),
 *   label_plural = @Translation("ILR employee positions"),
 *   label_count = @PluralTranslation(
 *     singular = "@count ILR employee positions",
 *     plural = "@count ILR employee positions",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\ilr_employee_position\ILREmployeePositionListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\ilr_employee_position\Form\ILREmployeePositionForm",
 *       "edit" = "Drupal\ilr_employee_position\Form\ILREmployeePositionForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *       "revision-delete" = \Drupal\Core\Entity\Form\RevisionDeleteForm::class,
 *       "revision-revert" = \Drupal\Core\Entity\Form\RevisionRevertForm::class,
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\ilr_employee_position\Routing\ILREmployeePositionHtmlRouteProvider",
 *       "revision" = \Drupal\Core\Entity\Routing\RevisionHtmlRouteProvider::class,
 *     },
 *   },
 *   base_table = "ilr_employee_position",
 *   revision_table = "ilr_employee_position_revision",
 *   show_revision_ui = TRUE,
 *   admin_permission = "administer ilr_employee_position",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision_id",
 *     "label" = "id",
 *     "uuid" = "uuid",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_uid",
 *     "revision_created" = "revision_timestamp",
 *     "revision_log_message" = "revision_log",
 *   },
 *   links = {
 *     "collection" = "/admin/content/ilr-employee-position",
 *     "add-form" = "/ilr-employee-position/add",
 *     "canonical" = "/ilr-employee-position/{ilr_employee_position}",
 *     "edit-form" = "/ilr-employee-position/{ilr_employee_position}",
 *     "delete-form" = "/ilr-employee-position/{ilr_employee_position}/delete",
 *     "delete-multiple-form" = "/admin/content/ilr-employee-position/delete-multiple",
 *     "revision" = "/ilr-employee-position/{ilr_employee_position}/revision/{ilr_employee_position_revision}/view",
 *     "revision-delete-form" = "/ilr-employee-position/{ilr_employee_position}/revision/{ilr_employee_position_revision}/delete",
 *     "revision-revert-form" = "/ilr-employee-position/{ilr_employee_position}/revision/{ilr_employee_position_revision}/revert",
 *     "version-history" = "/ilr-employee-position/{ilr_employee_position}/revisions",
 *   },
 *   field_ui_base_route = "entity.ilr_employee_position.settings",
 * )
 */
final class ILREmployeePosition extends RevisionableContentEntityBase implements ILREmployeePositionInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Remove the related persona from the cache when saving this position.
    Cache::invalidateTags($this->persona->entity->getCacheTags());
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['persona'] = BaseFieldDefinition::create('entity_reference')
      ->setRevisionable(TRUE)
      ->setLabel(t('Persona'))
      ->setRequired(TRUE)
      ->setSettings([
        'target_type' => 'persona',
        'handler' => 'default:persona',
        'handler_settings' => [
          'target_bundles' => ['ilr_employee' => 'ilr_employee'],
        ]
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 0,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'match_limit' => 20,
        ],
      ]);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setRevisionable(TRUE)
      ->setLabel(t('Title'))
      ->setRequired(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 1,
      ]);

    $fields['department'] = BaseFieldDefinition::create('entity_reference')
      ->setRevisionable(TRUE)
      ->setLabel(t('Department'))
      ->setRequired(TRUE)
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => ['organizational_units' => 'organizational_units'],
          'sort' => ['field' => 'name', 'direction' => 'asc'],
        ]
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'entity_reference_label',
        'settings' => ['link' => false],
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 0,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'match_limit' => 20,
        ],
      ]);


    $fields['primary'] = BaseFieldDefinition::create('boolean')
      ->setRevisionable(TRUE)
      ->setLabel(t('Primary'))
      ->setSetting('on_label', 'Primary')
      ->setSetting('off_label', 'No')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => FALSE,
        ],
        'weight' => 3,
      ]);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setRevisionable(TRUE)
      ->setLabel(t('Status'))
      ->setDefaultValue(TRUE)
      ->setSetting('on_label', 'Enabled')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => FALSE,
        ],
        'weight' => 4,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the ILR employee position was created.'))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 20,
      ]);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the ILR employee position was last edited.'));

    return $fields;
  }

}
