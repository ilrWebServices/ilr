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
      // Only run on full page requests, unless specified.
      if (context !== document && !settings.ilr_include_ajax) {
        return;
      }

      if (context == document && settings.content_attributes) {
        window.dataLayer = window.dataLayer || [];
        dataLayer.push(settings.content_attributes);
      }

      if (settings.ilr_webform_data) {
        window.dataLayer = window.dataLayer || [];
        dataLayer.push(settings.ilr_webform_data);
      }
    }
  }

})(window, document);
