/**
 * @file interaction-analytics.js
 *
 */
(function (window, document) {

  'use strict';

  /**
   * Add relevant content attributes to the dataLayer.
   */
  Drupal.behaviors.interactions = {
    attach: function (context, settings) {
      // Only run on full page requests, not ajax.
      if (context !== document) {
        return;
      }

      if (settings.content_attributes) {
        window.dataLayer = window.dataLayer || [];
        dataLayer.push(settings.content_attributes);
      }
    }
  }

})(window, document);
