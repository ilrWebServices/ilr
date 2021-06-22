<?php

namespace Drupal\ilr\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceEntityFormatter;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Plugin implementation of the 'collected_item_entity_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "collected_item_entity_formatter",
 *   label = @Translation("Collected item entity formatter"),
 *   description = @Translation("Display collected item entities rendered by entity_view()."),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class CollectedItemEntityFormatter extends EntityReferenceEntityFormatter {

  /**
   * {@inheritdoc}
   *
   * This is nearly identical to
   * EntityReferenceFormatterBase::getEntitiesToView(). The only difference is
   * that the _collected item entity_ is returned rather than the
   * collection_item entity itself. The returned entity also has the
   * `collection_item_url` property set for later use.
   */
  protected function getEntitiesToView(EntityReferenceFieldItemListInterface $items, $langcode) {
    $entities = [];

    foreach ($items as $delta => $item) {
      // Ignore items where no entity could be loaded in prepareView().
      if (!empty($item->_loaded)) {
        $collection_item = $item->entity;
        $entity = $collection_item->item->entity;

        // @see collection_item_path_preprocess_node().
        if ($entity instanceof NodeInterface && !$collection_item->isCanonical()) {
          $entity->collection_item_url = $collection_item->toUrl();
        }

        // Set the entity in the correct language for display.
        if ($entity instanceof TranslatableInterface) {
          $entity = \Drupal::service('entity.repository')->getTranslationFromContext($entity, $langcode);
        }

        $access = $this->checkAccess($entity);
        // Add the access result's cacheability, ::view() needs it.
        $item->_accessCacheability = CacheableMetadata::createFromObject($access);
        if ($access->isAllowed()) {
          // Add the referring item, in case the formatter needs it.
          $entity->_referringItem = $items[$delta];
          $entities[$delta] = $entity;
        }
      }
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   *
   * This is nearly identical to EntityReferenceEntityFormatter::viewElements().
   * It additionally adds the `url` cache context and the host entity cache tags
   * to the returned entities.
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $view_mode = $this->getSetting('view_mode');
    $elements = [];

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
      // Due to render caching and delayed calls, the viewElements() method
      // will be called later in the rendering process through a '#pre_render'
      // callback, so we need to generate a counter that takes into account
      // all the relevant information about this field and the referenced
      // entity that is being rendered.
      $recursive_render_id = $items->getFieldDefinition()->getTargetEntityTypeId()
        . $items->getFieldDefinition()->getTargetBundle()
        . $items->getName()
        // We include the referencing entity, so we can render default images
        // without hitting recursive protections.
        . $items->getEntity()->id()
        . $entity->getEntityTypeId()
        . $entity->id();

      if (isset(static::$recursiveRenderDepth[$recursive_render_id])) {
        static::$recursiveRenderDepth[$recursive_render_id]++;
      }
      else {
        static::$recursiveRenderDepth[$recursive_render_id] = 1;
      }

      // Protect ourselves from recursive rendering.
      if (static::$recursiveRenderDepth[$recursive_render_id] > static::RECURSIVE_RENDER_LIMIT) {
        $this->loggerFactory->get('entity')->error('Recursive rendering detected when rendering entity %entity_type: %entity_id, using the %field_name field on the %parent_entity_type:%parent_bundle %parent_entity_id entity. Aborting rendering.', [
          '%entity_type' => $entity->getEntityTypeId(),
          '%entity_id' => $entity->id(),
          '%field_name' => $items->getName(),
          '%parent_entity_type' => $items->getFieldDefinition()->getTargetEntityTypeId(),
          '%parent_bundle' => $items->getFieldDefinition()->getTargetBundle(),
          '%parent_entity_id' => $items->getEntity()->id(),
        ]);
        return $elements;
      }

      $view_builder = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId());
      $elements[$delta] = $view_builder->view($entity, $view_mode, $entity->language()->getId());

      // Cache this entity per the current url, since it is used in a curated
      // post listing.
      $elements[$delta]['#cache']['contexts'][] = 'url';

      // Add the host entity cache tags to invalidate this entity when the field
      // is changed.
      $host_entity = $this->getNonParagraphParentReferencingEntity($items->getEntity());
      $elements[$delta]['#cache']['tags'] = Cache::mergeTags($elements[$delta]['#cache']['tags'], $host_entity->getCacheTags());

      // Add a resource attribute to set the mapping property's value to the
      // entity's url. Since we don't know what the markup of the entity will
      // be, we shouldn't rely on it for structured data such as RDFa.
      if (!empty($items[$delta]->_attributes) && !$entity->isNew() && $entity->hasLinkTemplate('canonical')) {
        $items[$delta]->_attributes += ['resource' => $entity->toUrl()->toString()];
      }
    }

    return $elements;
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

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $target_type = $field_definition->getFieldStorageDefinition()->getSetting('target_type');
    return $target_type === 'collection_item';
  }

}
