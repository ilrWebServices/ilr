/**
 * @file banner_nav.js
 *
 * Defines the behavior of the section navigation component.
 */

 (function (window, document) {

  'use strict';

  /**
   * Move the section navigation into the banner region, if it is the first
   * component.
   */
  Drupal.behaviors.ilrSectionNavigation = {
    attach: function (context) {

      // Only run on full page requests, not ajax.
      if (context !== document) {
        return;
      }

      let banner_region = context.querySelector('.layout-banner');
      let components = context.querySelectorAll('.paragraph:not(.paragraph--type--section)');

      if (banner_region && components.length && components[0].classList.contains('cu-component--section-navigation')) {
        let comment = document.createComment('This menu was moved here via banner_nav.js');
        banner_region.appendChild(comment);
        banner_region.appendChild(components[0]);
        document.body.classList.add('include-banner-nav');
      }

    }
  };

})(window, document);
