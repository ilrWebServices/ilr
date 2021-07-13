<?php

namespace Drupal\collection_blogs\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\extended_post\ExtendedPostManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Returns responses for Blog feed route.
 */
class AtomFeed extends ControllerBase {

  /**
   * The module handler service.
   *
   * @var Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The extended post manager service.
   *
   * @var \Drupal\extended_post\ExtendedPostManager
   */
  protected $extendedPostManager;

  /**
   * The serializer xml encoder service.
   *
   * @var \Symfony\Component\Serializer\Encoder\XmlEncoder
   */
  protected $xmlEncoder;

  /**
   * Constructs this AtomFeed controller.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\extended_post\ExtendedPostManager $extended_post_manager
   *   The extended post manager service.
   * @param \Symfony\Component\Serializer\Encoder\XmlEncoder $xml_encoder
   *   The Symfony XML encoder.
   */
  public function __construct(ModuleHandlerInterface $module_handler, ExtendedPostManager $extended_post_manager, XmlEncoder $xml_encoder) {
    $this->moduleHandler = $module_handler;
    $this->extendedPostManager = $extended_post_manager;
    $this->xmlEncoder = $xml_encoder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('extended_post.manager'),
      new XmlEncoder()
    );
  }

  /**
   * The `content` route responds with the atom feed items.
   */
  public function content(CollectionInterface $collection, TermInterface $taxonomy_term = NULL, Request $request) {
    $response = new Response();

    if (!($post_types = $this->extendedPostManager->getPostTypes())) {
      return $response;
    }

    $allow_cross_posts = $this->moduleHandler->moduleExists('collection_item_path');
    $recent_updated_date = '';
    $collection_item_storage = $this->entityTypeManager()->getStorage('collection_item');
    $id = $taxonomy_term ? $taxonomy_term->uuid->value : $collection->uuid->value;

    $xml_array = [
      '@xmlns' => 'http://www.w3.org/2005/Atom',
      'title' => $collection->label() . ($taxonomy_term ? ': ' . $taxonomy_term->label() : ''),
      'id' => 'urn:uuid:' . $id,
      'updated' => &$recent_updated_date,
      'author' => [
        'name' => $collection->label(),
      ],
      'link' => [
        '@rel' => 'self',
        '@href' => $request->getUri(),
      ],
      'entry' => [],
    ];

    $blog_post_collection_item_query = $collection_item_storage->getQuery()
      ->condition('collection', $collection->id())
      ->condition('type', 'blog')
      ->condition('item.entity:node.status', 1)
      ->condition('item.entity:node.type', $post_types, 'IN')
      ->condition('item.entity:node.field_published_date', NULL, 'IS NOT NULL')
      ->sort('item.entity:node.field_published_date', 'DESC')
      ->range(0, 100);

    if ($taxonomy_term) {
      $term_group = $blog_post_collection_item_query->orConditionGroup();
      $term_group->condition('field_blog_categories', $taxonomy_term->id());
      $term_group->condition('field_blog_tags', $taxonomy_term->id());
      $blog_post_collection_item_query->condition($term_group);
    }

    $blog_post_collection_item_ids = $blog_post_collection_item_query->execute();

    if (empty($blog_post_collection_item_ids)) {
      return $response;
    }

    $blog_post_collection_items = $collection_item_storage->loadMultiple($blog_post_collection_item_ids);

    foreach ($blog_post_collection_items as $blog_post_collection_item) {
      $post = $blog_post_collection_item->item->entity;
      $post_pub_date = $post->field_published_date->date->getPhpDateTime();
      $post_pub_date_rfc_3339 = $post_pub_date->format(\DateTime::RFC3339);
      $post_updated_date = DrupalDateTime::createFromTimestamp($post->changed->value);
      $post_updated_date_rfc_3339 = $post_updated_date->format(\DateTime::RFC3339);
      $authors = [];
      $categories = [];

      if ($allow_cross_posts && !$blog_post_collection_item->isCanonical()) {
        $uuid = $blog_post_collection_item->uuid->value;
        $url = $blog_post_collection_item->toUrl('canonical', ['absolute' => TRUE])->toString();
      }
      else {
        $uuid = $post->uuid->value;
        $url = $post->toUrl('canonical', ['absolute' => TRUE])->toString();
      }

      $entry = [
        'title' => $post->label(),
        'link' => [
          '@href' => $url,
        ],
        'id' => 'urn:uuid:' . $uuid,
        'published' => $post_pub_date_rfc_3339,
        'updated' => $post_updated_date_rfc_3339,
        'summary' => $post->body->summary,
      ];

      if ($post->hasField('field_authors')) {
        foreach ($post->field_authors as $author) {
          $authors[] = ['name' => $author->entity->getDisplayName()];
        }

        if ($authors) {
          $entry['author'] = $authors;
        }
      }

      if ($blog_post_collection_item->field_blog_categories->isEmpty() === FALSE) {
        $categories[] = [
          '@term' => $blog_post_collection_item->field_blog_categories->entity->id(),
          '@label' => $blog_post_collection_item->field_blog_categories->entity->label(),
          '@scheme' => 'https://ilr.test/taxonomy/term/'
        ];
      }

      if ($blog_post_collection_item->field_blog_tags->isEmpty() === FALSE) {
        foreach ($blog_post_collection_item->field_blog_tags as $tag) {
          $categories[] = [
            '@term' => $tag->entity->id(),
            '@label' => $tag->entity->label(),
            '@scheme' => 'https://ilr.test/taxonomy/term/'];
        }
      }

      if ($categories) {
        $entry['category'] = $categories;
      }

      $xml_array['entry'][] = $entry;

      // Since $xml_array['updated'] is set to the referenced value of
      // $recent_updated_date, it will be updated when the variable is.
      if (empty($recent_updated_date)) {
        $recent_updated_date = $post_updated_date_rfc_3339;
      }
    }

    $context = [
      'xml_version' => '1.0',
      'xml_encoding' => 'utf-8',
      'xml_root_node_name' => 'feed',
    ];

    $response->setContent($this->xmlEncoder->encode($xml_array, 'xml', $context));
    $response->headers->set('Content-Type', 'application/atom+xml');
    return $response;
  }

}
