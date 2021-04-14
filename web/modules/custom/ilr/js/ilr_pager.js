/**
 * @file ilr_pager.js
 *
 * Defines the behavior the post listing pager enhancements.
 */

(function (window, document) {

  'use strict';

  /**
   * Registers behaviours related to iFrame display.
   */
  Drupal.behaviors.ilrEnhancePostlistingPager = {
    attach: function (context) {
      // Only run on full page requests, not ajax.
      if (context !== document) {
        return;
      }

      if (!('URLSearchParams' in window)) {
        return;
      }

      const url_params = new URLSearchParams(window.location.search);
      const post_listing_id = url_params.get('post_listing');

      if (!post_listing_id) {
        return;
      }

      let active_pager = context.getElementsByClassName('pager')[post_listing_id];

      if (!active_pager) {
        return;
      }

      let pager_parent = active_pager.parentElement;
      pager_parent.scrollIntoView({behavior: 'smooth'});
    }
  };

})(window, document);
