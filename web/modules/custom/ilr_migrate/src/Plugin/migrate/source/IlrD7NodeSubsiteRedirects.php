<?php

namespace Drupal\ilr_migrate\Plugin\migrate\source;

/**
 * Drupal 7 node source from database, expanded into one or more redirects.
 *
 * Some nodes in D7 were 'wrapped', meaning they use a subsite URL prefix on the
 * existing path alias. For example, '/news/inspired-courage-workers` could be
 * included in the ULI subsite, and its alias there would be
 * `/union-leadership-institute/news/inspired-courage-workers`.
 *
 * This source plugin extends our existing IlrD7Node plugin, but it alters the
 * alias with subsite prefixes and, importantly, it can generate more than one
 * row per node (e.g. if the node is tagged with two or more subsite terms).
 *
 * @MigrateSource(
 *   id = "ilr_d7_node_subsite_redirects",
 *   source_module = "node"
 * )
 */
class IlrD7NodeSubsiteRedirects extends IlrD7Node {

  // This is the mapping of D7 tags to path prefixes.
  protected $tag_redirect_prefixes = [
    'ics' => 'institute-for-compensation-studies',
    'Buffalo Co-Lab News' => 'buffalo-co-lab',
    'High Road News' => 'buffalo-co-lab',
    'democracy buff' => 'buffalo-co-lab',
    'visiting scholar buffalo' => 'buffalo-co-lab',
    'buff econ geo' => 'buffalo-co-lab',
    'ldi' => 'labor-dynamics-institute',
    'worker institute' => 'worker-institute',
    'worker' => 'worker-institute',
    'nysprojects' => 'worker-institute',
    'mobilizing against inequality' => 'mobilizing-against-inequality',
    'NLLI News' => 'national-labor-leadership-initiative',
    'NLLI' => 'national-labor-leadership-initiative',
    'uli' => 'union-leadership-institute',
  ];

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() {
    $yielded_ids = [];

    // This runs the original iterator, which is a query result set with one row
    // per D7 node.
    foreach (parent::initializeIterator() as $key => $row) {
      if ($row['node_terms']) {
        $tags = explode(',', strtolower($row['node_terms']));
        $alias_orig = $row['node_alias'];

        // Create one or more rows per node id and prefix combo. This is our new source key.
        foreach ($this->tag_redirect_prefixes as $tag => $tag_redirect_prefix) {
          if (in_array($tag, $tags)) {
            $id = $row['nid'] . '-' . $tag_redirect_prefix;

            if (in_array($id, $yielded_ids)) {
              continue;
            }

            $row['nid_prefix_id'] = $id;
            $row['node_alias'] = $tag_redirect_prefix . '/' . $alias_orig;
            yield $id => $row;
            $yielded_ids[] = $id;
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function count($refresh = FALSE) {
    return iterator_count($this->initializeIterator());
  }

  /**
   * {@inheritdoc}
   *
   * Don't try to join to the migrate map table because our source id is
   * generated dynamically above.
   */
  protected function mapJoinable() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['nid_prefix_id']['type'] = 'string';
    return $ids;
  }

}
