<?php

namespace Drupal\ilr\Plugin\Filter;

use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\FilterProcessResult;
use Drupal\Component\Utility\Html;
use Drupal\Core\Url;

/**
 * Provides a filter to fix hard-coded links.
 *
 * @Filter(
 *   id = "filter_internal_links",
 *   title = @Translation("ILR Link Fixer"),
 *   description = @Translation("Fix problematic internal links (e.g. full host, editor-only host, or /node/ID paths)."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
 * )
 */
class FilterInternalLinks extends FilterBase {

  // See also ProblemInternalLinkValidator::internalHosts.
  protected $internalHosts = [
    'ilr.cornell.edu',
    'www.ilr.cornell.edu',
    'd8-edit.ilr.cornell.edu',
    'edit.ilr.cornell.edu',
  ];

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $result = new FilterProcessResult($text);
    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);

    // Find all of the <a> elements with an href attribute.
    foreach ($xpath->query('//a[@href]') as $node) {
      $parts = parse_url($node->getAttribute('href'));

      // Test the href for problems. URLs with internal (or editor-only) hosts
      // need cleanup. As do internal links using /node/ID paths.
      $is_internal_host = isset($parts['host']) && in_array($parts['host'], $this->internalHosts);
      $is_node_path = empty($parts['host']) && !empty($parts['path']) && preg_match('|^/node/\d+$|', $parts['path']);

      // If this href is problematic, clean it up. This is done by passing the
      // URL parts into the Url::fromUri() method. This method helpfully
      // converts URLs with the 'internal:' scheme into internal root paths,
      // using aliases if they exist. Note that we don't preserve the host here,
      // since this processor is only meant to fix problematic internal links.
      if ($is_internal_host || $is_node_path) {
        $options = [];

        if (!empty($parts['fragment'])) {
          $options['fragment'] = $parts['fragment'];
        }

        if (!empty($parts['query'])) {
          parse_str($parts['query'], $query_opts);
          $options['query'] = $query_opts;
        }

        if (empty($parts['path'])) {
          $parts['path'] = '/';
        }

        $url = Url::fromUri('internal:' . trim($parts['path']), $options);
        $node->setAttribute('href', $url->toString());
      }
    }

    $result->setProcessedText(Html::serialize($dom));
    return $result;
  }

}
