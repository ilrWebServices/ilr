<?php

namespace Drupal\ilr\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\collection\Event\CollectionEvents;
use Symfony\Component\EventDispatcher\Event;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

/**
 * Class CollectionEventSubscriber.
 */
class CollectionEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new CollectionBlogsSubscriber object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger, TranslationInterface $string_translation) {
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      CollectionEvents::COLLECTION_ENTITY_CREATE => 'collectionCreate',
    ];
  }

  /**
   * Process the COLLECTION_ENTITY_CREATE event.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The dispatched event.
   */
  public function collectionCreate(Event $event) {
    $collection = $event->collection;
    if ($collection->bundle() == 'blog') {
      // Create a section paragraph with a collection listing nested inside it
      // and add it to the collection entity components field
      $section = Paragraph::create([
        'type' => 'section',
      ]);

      $listing = Paragraph::create([
        'type' => 'simple_collection_listing',
        'field_collection' => [
          'target_id' => $collection->id(),
        ],
      ]);

      $settings = [
        'post_listing' => [],
        'list_styles' => ['list_style' => 'grid'],
      ];
      $listing->setAllBehaviorSettings($settings);
      $listing->save();
      $section->field_components->appendItem($listing);
      $section->save();
      $collection->field_sections->appendItem($section);

      if ($collection->save()) {
        $collection_item_add_url = Url::fromRoute('collection_item.new.node', ['collection' => $collection->id()]);
        $this->messenger->addMessage($this->t('%collection_name created. <a href="@collection_add_url">Add content</a> to your blog.', [
          '%collection_name' => $collection->label(),
          '@collection_add_url' => $collection_item_add_url->toString(),
        ]));
      }

      // Load the config for a typical blog category.
      $category_storage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');
      $entity_field_manager = \Drupal::service('entity_field.manager');
      $display_repository = \Drupal::service('entity_display.repository');
      $field_instance_config = $this->entityTypeManager->getStorage('field_config');
      $category_form_display = $display_repository->getFormDisplay('taxonomy_term', 'blog_2_categories');
      $category_view_display = $display_repository->getViewDisplay('taxonomy_term', 'blog_2_categories');

      // Load the vocabulary that was just created and added to this collection.
      $collection_items = $collection->findItemsByAttribute('blog_taxonomy_categories', TRUE);

      if (empty($collection_items)) {
        return;
      }

      $collected_item = reset($collection_items);
      $new_category = $collected_item->item->entity;
      $new_category_form_display = $display_repository->getFormDisplay('taxonomy_term', $new_category->id());
      $new_category_view_display = $display_repository->getViewDisplay('taxonomy_term', $new_category->id());

      foreach ($entity_field_manager->getFieldDefinitions('taxonomy_term', 'blog_2_categories') as $category_field_name => $category_field_def) {
        // `FieldConfig` definitions are user created fields (i.e. `field_*`).
        // This filters out base fields, which would be `BaseFieldDefinition`s.
        if ($category_field_def instanceof FieldConfig) {
          // Load the field config
          $base_field_storage_config = FieldStorageConfig::loadByName('taxonomy_term', $category_field_name);
          // Copy standard field instances to this vocabulary.
          $new_field_config = $field_instance_config->create([
            'field_storage' => $base_field_storage_config,
            'bundle' => $new_category->id(),
            'label' => $category_field_def->getLabel(),
            'settings' => $category_field_def->getSettings(),
          ]);
          $new_field_config->save();

          // Configure form and view modes to match fields, too.
          // @todo: fix this to work with layout builder settings.
          $new_category_form_display->setComponent($category_field_name, $category_form_display->getComponent($category_field_name))->save();
          $new_category_view_display->setComponent($category_field_name, $category_view_display->getComponent($category_field_name))->save();
        }
      }
    }
  }
}
