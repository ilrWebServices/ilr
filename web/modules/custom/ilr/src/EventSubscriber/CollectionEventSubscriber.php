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
   * Process the COLLECTION_ITEM_FORM_CREATE event.
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
        'type' => 'collection_listing',
        'field_collection' => [
          'target_id' => $collection->id(),
        ],
      ]);

      $listing->save();
      $section->field_components->appendItem($listing);
      $section->save();
      $collection->field_components->appendItem($section);

      if ($collection->save()) {
        $collection_item_add_url = Url::fromRoute('collection_item.new.node', ['collection' => $collection->id()]);
        $this->messenger->addMessage($this->t('%collection_name created. <a href="@collection_add_url">Add content</a> to your blog.', [
          '%collection_name' => $collection->label(),
          '@collection_add_url' => $collection_item_add_url->toString(),
        ]));
      }
    }
  }
}
