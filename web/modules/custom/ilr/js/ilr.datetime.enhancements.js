/**
 * @file ilr.datetime.enhancements.js
 *
 * Sets a default time when a date is selected.
 */

(function () {

  'use strict';

  Drupal.behaviors.setTimeOnDateChange = {
    attach: function (context) {
      document.addEventListener('change', function (event) {
        const input = event.target;

        if (input.matches('.form-date')) {
          const container = input.closest('.container-inline');
          const timeField = container.querySelector('input[type="time"]');

          if (timeField && !timeField.value) {
            const now = new Date();
            const hh = String(now.getHours()).padStart(2, '0');
            timeField.value = `${hh}:00`; // Nearest hour only.
          }
        }
      });
    }
  };
})();
