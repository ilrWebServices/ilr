<?php

namespace Drupal\ilr\StackMiddleware;

use Drupal\page_cache\StackMiddleware\PageCache as CorePageCache;
use Symfony\Component\HttpFoundation\Request;

/**
 * Executes the page caching before the main kernel takes over the request.
 */
class PageCache extends CorePageCache {

  /**
   * {@inheritdoc}
   */
  protected function getCacheId(Request $request) {
    // This is a copy of parent::getCacheId() with the addition of a short
    // hashed version of the pvp_stored_variables cookie to the cache_id. This
    // results in a LOT more copies of the same page in the cache, but allows
    // forms with pre-filled values based on values in this cookie to work as
    // desired.
    if (!isset($this->cid)) {
      $cid_parts = [
        $request->getSchemeAndHttpHost() . $request->getRequestUri(),
        $request->getRequestFormat(NULL),
        crc32($request->cookies->get('pvp_stored_variables')) ,
      ];
      $this->cid = implode(':', $cid_parts);
    }
    return $this->cid;
  }

}
