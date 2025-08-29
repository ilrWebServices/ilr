/**
 * @file interaction-analytics.js
 *
 */
(function (window, document) {

  'use strict';

  /**
   * Add relevant webform submission data to the dataLayer.
   */
  Drupal.behaviors.webform_datalayer = {
    attach: function (context, settings) {

      function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return decodeURIComponent(parts.pop().split(';').shift());
      }

      try {
        const submissionCookie = getCookie("ilr_datalayer_submission");
        if (submissionCookie) {
          const data = atob(submissionCookie);

          if (data) {
            window.dataLayer = window.dataLayer || [];
            dataLayer.push(JSON.parse(data));

            // Delete the cookie so that this submission doesn't get added to
            // the datalayer again.
            document.cookie = "ilr_datalayer_submission=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/";
          }
        }
      } catch (error) {
        // Fail silently.
      }
    }
  }

})(window, document);
