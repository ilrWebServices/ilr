<?php

namespace Drupal\collection_blogs\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\collection\Entity\CollectionInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

class AtomFeed extends ControllerBase {

  public function content(CollectionInterface $collection) {
    $entity_type_manager = \Drupal::service('entity_type.manager');
    $response = new Response();
    $xmlEncoder = new XmlEncoder();
    $recent_pub_date = '';
    $xml_array = [
      '@xmlns' => 'http://www.w3.org/2005/Atom',
      'title' => $collection->label(),
      'id' => 'urn:uuid:' . $collection->uuid->value,
      'updated' => &$recent_pub_date,
      'entry' => [],
    ];

    $blog_post_collection_item_ids = \Drupal::entityQuery('collection_item')
      ->condition('collection', $collection->id())
      ->condition('type', 'blog')
      ->condition('item.entity:node.status', 1)
      ->condition('item.entity:node.type', 'post')
      ->sort('item.entity:node.field_published_date', 'DESC')
      ->execute();

    $blog_post_collection_items = $entity_type_manager->getStorage('collection_item')->loadMultiple($blog_post_collection_item_ids);

    foreach ($blog_post_collection_items as $blog_post_collection_item) {
      $post = $blog_post_collection_item->item->entity;
      $post_pub_date = $post->field_published_date->date->getPhpDateTime();
      $post_date_rfc_3339 = $post_pub_date->format(\DateTime::RFC3339);

      $xml_array['entry'][] = [
        'title' => $post->label(),
        'link' => [
          '@href' => $post->toUrl('canonical', ['absolute' => TRUE])->toString(),
        ],
        'id' => 'urn:uuid:' . $post->uuid->value,
        'updated' => $post_date_rfc_3339,
        'summary' => $post->body->summary,
        'category' => [
          '@term' => $blog_post_collection_item->field_blog_categories->entity->label()
        ],
      ];

      // Since $xml_array['updated'] is set to the referenced value of
      // $recent_pub_date, it will be updated when the variable is.
      if (empty($recent_pub_date)) {
        $recent_pub_date = $post_date_rfc_3339;
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
