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
      if (context !== document && !settings.include_ajax) {
        return;
      }

      if (context == document && settings.content_attributes) {
        window.dataLayer = window.dataLayer || [];
        dataLayer.push(settings.content_attributes);
      }

      if (settings.webform_data) {
        window.dataLayer = window.dataLayer || [];
        dataLayer.push(settings.webform_data);
      }
    }
  }

})(window, document);
