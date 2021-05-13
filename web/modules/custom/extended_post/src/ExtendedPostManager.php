<?php

namespace Drupal\extended_post;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines a class for listening for node bundle events.
 */
class ExtendedPostManager {

  use StringTranslationTrait;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity display repository service.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The node bundle.
   *
   * @var string
   */
  protected $bundle;

  /**
   * Constructs a new ExtendedPostManager service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityDisplayRepositoryInterface $entity_display_repository, ModuleHandlerInterface $module_handler) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityDisplayRepository = $entity_display_repository;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Public API for configuring "extended" post types.
   *
   * @param string $bundle
   * @return void
   */
  public function configureExtendedPostType($bundle) {
    $this->bundle = $bundle;

    foreach (['body', 'field_components', 'field_published_date', 'field_representative_image'] as $field_name) {
      $this->addOrUpdateField($field_name);
      $this->configureFormDisplayComponent($field_name);
    }

    if ($this->moduleHandler->moduleExists('collection_blogs')) {
      // Load the blog collection item so we can enable this bundle too.
      $blog_item = $this->entityTypeManager->getStorage('collection_item_type')->load('blog');
      $allowed_bundles = $blog_item->getAllowedBundles();
      $allowed_bundles[] = 'node.' . $bundle;
      $blog_item->set('allowed_bundles', $allowed_bundles);
      $blog_item->save();
    }
  }

  /**
   * Add or update the field config (instance).
   *
   * @param string $field_name
   * @return void
   */
  protected function addOrUpdateField($field_name) {
    $field_storage = FieldStorageConfig::loadByName('node', $field_name);

    if ($field = FieldConfig::loadByName('node', $this->bundle, $field_name)) {
      // Update some values here, such as description, etc.
      $config = array_merge($field, $this->getFieldConfiguration($field_name));
    }
    else {
      $config = array_merge($this->getFieldConfiguration($field_name), ['field_storage' => $field_storage, 'bundle' => $this->bundle]);
      $field = FieldConfig::create($config);
    }
    $field->save();
  }

  /**
   * Configure the form display based on the current state of post
   * configuration.
   *
   * @param string $component_name
   * @return void
   */
  protected function configureFormDisplayComponent($component_name) {
    $base_post_form = $this->entityDisplayRepository->getFormDisplay('node', 'post');
    $extended_post_display = $this->entityDisplayRepository->getFormDisplay('node', $this->bundle);
    $extended_post_display->setComponent($component_name, $base_post_form->getComponent($component_name));
    $extended_post_display->save();
  }

  protected function getFieldConfiguration($field_name) {
    $field_config = [
      'body' => [
        'label' => $this->t('Intro Text'),
        'description' => $this->t('Summaries added here are used in smaller components, such as cards. The summary will also be used when sharing this page via social media.'),
        'settings' => ['display_summary' => TRUE, 'required_summary' => TRUE],
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
      ],
      'field_components' => [
        'bundle' => $this->bundle,
        'label' => 'Post Content',
        'settings' => [
          'handler' => 'default:paragraph',
          'handler_settings' => [
            'negate' => 0,
            'target_bundles' => ['image' => 'image', 'rich_text' => 'rich_text', 'video' => 'video'],
            'target_bundles_drag_drop' => [
              'image' => ['enabled' => TRUE],
              'rich_text' => ['enabled' => TRUE],
              'video' => ['enabled' => TRUE],
            ],
          ],
        ],
        'field_type' => 'entity_reference_revisions',
      ],
      'field_published_date' => [
        'bundle' => $this->bundle,
        'label' => 'Published Date',
        'description' => 'The published date for this post.',
        'required' => TRUE,
        'default_value' => [['default_date_type' => 'now', 'default_date' => 'now']],
        'field_type' => 'datetime',
      ],
      'field_representative_image' => [
        'bundle' => $this->bundle,
        'label' => 'Representative Image',
        'description' => 'This image may be used: As the banner background; when sharing this content on social media; or when representing it a smaller component, such as a teaser.',
        'settings' => [
          'handler' => 'default:media',
          'handler_settings' => [
            'target_bundles' => ['image' => 'image'],
            'sort' => ['field' => '_none'],
            'auto_create' => FALSE,
            'auto_create_bundle' => '',
          ]
        ],
        'field_type' => 'entity_reference',
      ],
    ];

    return $field_config[$field_name];
  }

  /**
   * Get a list of node types that are post-like.
   *
   * @return array
   *   An array of node types that are considered posts.
   */
  public function getPostTypes() {
    $post_types = [];

    foreach ($this->entityTypeManager->getStorage('node_type')->loadMultiple() as $bundle_name => $type) {
      if ($type->getThirdPartySetting('extended_post', 'extends_posts')) {
        $post_types[] = $bundle_name;
      }
    }

    return $post_types;
  }

}
