<?php

namespace Drupal\autocomplete_decorator;

use Drupal\Core\Entity\EntityAutocompleteMatcher;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Tags;
use Drupal\Core\Entity\EntityInterface;

/**
 * Class EntityAutocompleteMatcherOverride.
 *
 * Decorates matches returned by the autocomplete service.
 */
class EntityAutocompleteMatcherOverride extends EntityAutocompleteMatcher {

  use StringTranslationTrait;

  /**
   * The entity reference selection handler plugin manager.
   *
   * @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface
   */
  protected $selectionManager;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Entity\EntityTypeBundleInfoInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * OriginalServiceOverride constructor.
   *
   * @param \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface $selection_manager
   *   The entity reference selection handler plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   */
  public function __construct(SelectionPluginManagerInterface $selection_manager, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    $this->selectionManager = $selection_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * Gets matched labels based on a given search string.
   */
  public function getMatches($target_type, $selection_handler, $selection_settings, $string = '') {
    $matches = [];

    $options = $selection_settings + [
      'target_type' => $target_type,
      'handler' => $selection_handler,
    ];
    $handler = $this->selectionManager->getInstance($options);

    if (isset($string)) {
      // Get an array of matching entities.
      $match_operator = !empty($selection_settings['match_operator']) ? $selection_settings['match_operator'] : 'CONTAINS';
      $match_limit = isset($selection_settings['match_limit']) ? (int) $selection_settings['match_limit'] : 10;
      $entity_labels = $handler->getReferenceableEntities($string, $match_operator, $match_limit);

      // Loop through the entities and convert them into autocomplete output.
      foreach ($entity_labels as $values) {
        foreach ($values as $entity_id => $label) {
          $entity = $this->entityTypeManager->getStorage($target_type)->load($entity_id);

          if ($entity->getEntityTypeId() === 'collection_item') {
            $label .= ' [' . $this->getBundleLabel($entity->item->entity) . ' ' . $this->t('in') . ' ' . $entity->collection->entity->label() . ($entity->isCanonical() ? '*' : '') . ']';
          }
          else {
            $label .= ' (' . $entity_id . ') [' . $this->getBundleLabel($entity) . ']';
          }

          $key = "$label ($entity_id)";
          // Strip things like starting/trailing white spaces, line breaks and
          // tags.
          $key = preg_replace('/\s\s+/', ' ', str_replace("\n", '', trim(Html::decodeEntities(strip_tags($key)))));
          // Names containing commas or quotes must be wrapped in quotes.
          $key = Tags::encode($key);
          $matches[] = ['value' => $key, 'label' => $label];
        }
      }
    }

    return $matches;
  }

  /**
   * Return a human readable bundle label, if possible, for an entity.
   *
   * @param EntityInterface $entity
   *
   * @return string
   *   The human readable bundle label or the machine name.
   */
  protected function getBundleLabel(EntityInterface $entity) {
    if ($bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId())) {
      return $bundle_info[$entity->bundle()]['label'];
    }

    return $entity->bundle();
  }

}
