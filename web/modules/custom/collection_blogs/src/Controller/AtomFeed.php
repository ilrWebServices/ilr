<?php

namespace Drupal\collection_blogs\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\collection\Entity\CollectionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Drupal\Core\Datetime\DrupalDateTime;

class AtomFeed extends ControllerBase {

  public function content(CollectionInterface $collection, Request $request) {
    $entity_type_manager = \Drupal::service('entity_type.manager');
    $response = new Response();
    $xmlEncoder = new XmlEncoder();
    $allow_cross_posts = \Drupal::moduleHandler()->moduleExists('collection_item_path');
    $recent_updated_date = '';
    $xml_array = [
      '@xmlns' => 'http://www.w3.org/2005/Atom',
      'title' => $collection->label(),
      'id' => 'urn:uuid:' . $collection->uuid->value,
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

    $blog_post_collection_item_ids = \Drupal::entityQuery('collection_item')
      ->condition('collection', $collection->id())
      ->condition('type', 'blog')
      ->condition('item.entity:node.status', 1)
      ->condition('item.entity:node.type', ['post', 'story'], 'IN')
      ->condition('item.entity:node.field_published_date', NULL, 'IS NOT NULL')
      ->sort('item.entity:node.field_published_date', 'DESC')
      ->execute();

    $blog_post_collection_items = $entity_type_manager->getStorage('collection_item')->loadMultiple($blog_post_collection_item_ids);

    foreach ($blog_post_collection_items as $blog_post_collection_item) {
      $post = $blog_post_collection_item->item->entity;
      $post_pub_date = $post->field_published_date->date->getPhpDateTime();
      $post_pub_date_rfc_3339 = $post_pub_date->format(\DateTime::RFC3339);
      $post_updated_date = DrupalDateTime::createFromTimestamp($post->changed->value);
      $post_updated_date_rfc_3339 = $post_updated_date->format(\DateTime::RFC3339);
      $authors = [];

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

      foreach ($post->field_authors as $author) {
        $authors[] = ['name' => $author->entity->getDisplayName()];
      }

      if ($authors) {
        $entry['author'] = $authors;
      }

      if ($blog_post_collection_item->field_blog_categories->isEmpty() === FALSE) {
        $entry['category'] = [
          '@term' => $blog_post_collection_item->field_blog_categories->entity->label()
        ];
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

    $response->setContent($xmlEncoder->encode($xml_array, 'xml', $context));
    $response->headers->set('Content-Type', 'application/atom+xml');
    return $response;
 }

}
