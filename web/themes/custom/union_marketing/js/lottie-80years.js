/**
 * @file
 * Lottie animation for 80 years logo.
 */
(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.lottie80Years = {
    attach: function (context, settings) {
      // Initialize Lottie animation for 80 years logo
      const elements = document.querySelectorAll('.lottie-80years-container');

      elements.forEach(element => {
        if (element.classList.contains('lottie-processed')) {
          return;
        }

        element.classList.add('lottie-processed');

        // Create Lottie animation
        lottie.loadAnimation({
          container: element,
          renderer: 'svg',
          loop: false,
          autoplay: true,
          path: '/themes/custom/union_marketing/data/80years.json'
        });
      });
    }
  };
})(jQuery, Drupal);
