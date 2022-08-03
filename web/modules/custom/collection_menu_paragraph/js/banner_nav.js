/**
 * @file banner_nav.js
 *
 * Defines the behavior of the section navigation component.
 */

 (function (window, document) {

  'use strict';

  /**
   * Move the section navigation into the banner region, if present.
   */
  Drupal.behaviors.ilrSectionNavigation = {
    attach: function (context) {
      // Only run on full page requests, not ajax.
      if (context !== document) {
        return;
      }

      let section_navigation = context.querySelector('.cu-component--section-navigation');
      let banner_region = context.querySelector('.layout-banner');

      if (section_navigation && banner_region) {
        let comment = document.createComment('This menu was moved here via banner_nav.js');
        banner_region.appendChild(comment);
        banner_region.appendChild(section_navigation);

        document.body.classList.add('include-banner-nav');
      }
    }
  };

})(window, document);
