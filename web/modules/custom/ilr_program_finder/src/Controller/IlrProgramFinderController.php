<?php

declare(strict_types=1);

namespace Drupal\ilr_program_finder\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for ILR Program Finder routes.
 */
final class IlrProgramFinderController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function __invoke(Request $request): CacheableJsonResponse {
    /** @var \Drupal\search_api\Entity\Index $index  */
    $index = $this->entityTypeManager()->getStorage('search_api_index')->load('program_finder_data');

    $query = $index->query();
    $query->addCondition('upcoming_dates', time(), '>');
    $query->sort('upcoming_dates');
    $results = $query->execute();

    $data = [];

    /** @var \Drupal\search_api\Item\Item $item  */
    foreach ($results->getResultItems() as $item) {
      $data[] = [
        'id' => $item->getId(),
        // 'score' => $item->getScore(),
        'group' => $item->getField('group')->getValues()[0],
        'title' => $item->getField('title')->getValues()[0] ?? '',
        'status' => $item->getField('status')->getValues()[0],
        'delivery_method' => $item->getField('delivery_method')->getValues()[0] ?? '',
        'summary' => $item->getField('summary')->getValues()[0] ?? '',
        'topic' => $item->getField('topic')->getValues() ?? [],
        'type' => $item->getField('type')->getValues()[0] ?? '',
        'upcoming_dates' => $item->getField('upcoming_dates')->getValues(),
        ];
    }

    $response = new CacheableJsonResponse(['data' => $data]);

    $cache_metadata = new CacheableMetadata();
    $cache_metadata->setCacheTags(['ilr_program_finder:data']);
    $cache_metadata->setCacheContexts(['url.query_args']);
    $cache_metadata->setCacheMaxAge(5);

    $response->addCacheableDependency($cache_metadata);

    return $response;
  }

}
