<?php

namespace Drupal\ilr\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\collection\Event\CollectionEvents;
use Symfony\Component\EventDispatcher\Event;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Entity\EntityDisplayRepository;
use Drupal\Component\Uuid\Php as Uuid;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;

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
   * The entity display repository service.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepository
   */
  protected $entityDisplayRepository;

  /**
   * The UUID service.
   *
   * @var \Drupal\Component\Uuid\Php
   */
  protected $uuid;

  /**
   * Constructs a new CollectionEventSubscriber object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger, TranslationInterface $string_translation, EntityDisplayRepository $entity_display_repository, Uuid $uuid) {
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->stringTranslation = $string_translation;
    $this->entityDisplayRepository = $entity_display_repository;
    $this->uuid = $uuid;
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

      // Load the vocabulary that was created with this collection.
      $collection_items = $collection->findItemsByAttribute('blog_taxonomy_categories', TRUE);

      if (empty($collection_items)) {
        return;
      }

      $collected_item = reset($collection_items);
      $category = $collected_item->item->entity;
      $category_form_display = $this->entityDisplayRepository->getFormDisplay('taxonomy_term', $category->id());

      // Configure the category fields and form display.
      foreach ($this->getFieldConfiguration($category->id()) as $field_name => $field) {
        $new_field_config = $this->entityTypeManager->getStorage('field_config')->create($field['field_config']);
        $new_field_config->save();
        $category_form_display->setComponent($field_name, $field['form_display']);
      }

      $category_form_display->removeComponent('description');
      $category_form_display->save();

      // Configure the category view display layout builder sections.
      $category_view_display = $this->entityDisplayRepository->getViewDisplay('taxonomy_term', $category->id());
      $category_view_display->enableLayoutBuilder();
      $category_view_display->save();
      $category_view_display->removeAllSections();

      foreach ($this->getCategoryLayoutSections($category) as $section) {
        $category_view_display->appendSection($section);
      }

      $category_view_display->save();
    }
  }

  /**
   * Generate the "template" for field instance and form display config.
   *
   * @param int $category_id
   *
   * @return array
   *   An array of field config arrays.
   */
  protected function getFieldConfiguration($category_id) {
    return [
        'field_body' => [
          'field_config' => [
            'field_storage' => FieldStorageConfig::loadByName('taxonomy_term', 'field_body'),
            'third_party_settings' => [
              'allowed_formats' => [
                'basic_formatting' => 'basic_formatting',
                'basic_formatting_with_media' => '0',
                'full_html' => '0',
                'inline_svg' => '0',
                'plain_text' => '0',
              ],
              'summary_word_limit' => [
                'summary_word_limit_count' => '50',
              ],
            ],
            'bundle' => $category_id,
            'label' => 'Intro text',
            'description' => 'If there is intro text, it is added to the banner section of the page and can be used in social media posts. Summaries added here are also used in smaller components, such as cards. The summary will also be used when sharing this page via social media.',
            'settings' => [
              'display_summary' => true,
              'required_summary' => true,
            ],
          ],
          'form_display' => [
            'weight' => 1,
            'settings' => [
              'rows' => 9,
              'summary_rows' => 3,
              'placeholder' => '',
              'show_summary' => false,
            ],
            'type' => 'text_textarea_with_summary',
          ],
        ],
        'field_sections' => [
          'field_config' => [
            'field_storage' => FieldStorageConfig::loadByName('taxonomy_term', 'field_sections'),
            'bundle' => $category_id,
            'label' => 'Page content',
            'settings' => [
              'handler' => 'default:paragraph',
              'handler_settings' => [
                'negate' => 0,
                'target_bundles' => ['section' => 'section'],
                'target_bundles_drag_drop' => [
                  'section' => ['enabled' => true],
                ],
              ],
            ],
            'field_type' => 'entity_reference_revisions'
          ],
          'form_display' => [
            'weight' => 2,
            'settings' => [
              'title' => 'section',
              'title_plural' => 'sections',
              'edit_mode' => 'closed',
              'closed_mode' => 'summary',
              'autocollapse' => 'all',
              'closed_mode_threshold' => 2,
              'add_mode' => 'dropdown',
              'form_display_mode' => 'default',
              'default_paragraph_type' => 'section',
              'features' => [
                'collapse_edit_all' => 'collapse_edit_all',
                'duplicate' => 0,
                'add_above' => 0,
              ],
            ],
            'type' => 'paragraphs_previewer',
            'region' => 'content',
          ],
        ],
        'field_representative_image' => [
          'field_config' => [
            'field_storage' => FieldStorageConfig::loadByName('taxonomy_term', 'field_representative_image'),
            'bundle' => $category_id,
            'label' => 'Representative image',
            'description' => 'This image may be used: As the banner background; when sharing this content on social media; or when representing it a smaller component, such as a teaser.',
            'settings' => [
              'handler' => 'default:media',
              'handler_settings' => [
                'target_bundles' => ['image' => 'image'],
                'sort' => ['field' => '_none'],
                'auto_create' => false,
                'auto_create_bundle' => '',
              ],
            ],
            'field_type' => 'entity_reference'
          ],
          'form_display' => [
            'weight' => 3,
            'type' => 'media_library_widget',
            'region' => 'content',
          ],
        ],
      ];
  }

  /**
   * Generate the "template" for layout builder sections for category pages.
   *
   * @param TaxonomyVocabulary $category
   *
   * @return array
   *   An array of layout builder sections
   */
  protected function getCategoryLayoutSections($category) {
    $sections = [];

    $sections[] = new Section('layout_onecol', ['label' => 'Blog banner'], [
      new SectionComponent($this->uuid->generate(), 'content', [
        'id' => 'extra_field_block:taxonomy_term:' . $category->id() . ':blog_collection',
        'label' => 'Blog',
        'provider' => 'layout_builder',
        'label_display' => 0,
        'context_mapping' => [
          'entity' => 'layout_builder.entity',
        ],
      ]),
    ]);

    $sections[] = new Section('layout_cu_section', ['label' => 'Content header'], [
      new SectionComponent($this->uuid->generate(), 'main', [
        'id' => 'field_block:taxonomy_term:' . $category->id() . ':name',
        'label' => 'Name',
        'provider' => 'layout_builder',
        'label_display' => 0,
        'context_mapping' => [
          'entity' => 'layout_builder.entity',
        ],
        'formatter' => [
          'label' => 'hidden',
          'type' => 'string',
          'settings' => [
            'link_to_entity' => false
          ],
        ],
        'context_mapping' => [
          'entity' => 'layout_builder.entity',
          'view_mode' => 'view_mode',
        ],
      ]),
      new SectionComponent($this->uuid->generate(), 'main', [
        'id' => 'field_block:taxonomy_term:' . $category->id() . ':field_body',
        'label' => 'Intro text',
        'provider' => 'layout_builder',
        'label_display' => 0,
        'context_mapping' => [
          'entity' => 'layout_builder.entity',
        ],
        'formatter' => [
          'label' => 'hidden',
          'type' => 'text_default',
        ],
        'context_mapping' => [
          'entity' => 'layout_builder.entity',
          'view_mode' => 'view_mode',
        ],
        'weight' => 1,
      ]),
    ]);

    $sections[] = new Section('layout_onecol', ['label' => 'Page content'], [
      new SectionComponent($this->uuid->generate(), 'content', [
        'id' => 'field_block:taxonomy_term:' . $category->id() . ':field_sections',
        'label' => 'Page content',
        'provider' => 'layout_builder',
        'label_display' => 0,
        'context_mapping' => [
          'entity' => 'layout_builder.entity',
        ],
        'formatter' => [
          'label' => 'hidden',
          'type' => 'entity_reference_revisions_entity_view',
          'settings' => [
            'view_mode' => 'default'
          ],
        ],
        'context_mapping' => [
          'entity' => 'layout_builder.entity',
          'view_mode' => 'view_mode',
        ],
      ]),
    ]);

    $sections[] = new Section('layout_cu_section', ['label' => 'Items'], [
      new SectionComponent($this->uuid->generate(), 'main', [
        'id' => 'extra_field_block:taxonomy_term:' . $category->id() . ':collection_items_category_term',
        'label' => $category->label(),
        'provider' => 'layout_builder',
        'label_display' => 0,
        'context_mapping' => [
          'entity' => 'layout_builder.entity',
        ],
      ]),
    ]);

    return $sections;
  }
}
