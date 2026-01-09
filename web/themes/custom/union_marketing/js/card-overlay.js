/**
 * @file card-overlay.js
 *
 * When a card overlay is set to reveal text on hover, this JavaScript
 * will handle the animation.
 */

(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.cardOverlay = {
    attach: function (context, settings) {
      $('.cu-component--card-overlay.cu-section--reveal-on-hover', context).each(function() {
        var $container = $(this);
        var $cards = $container.find('.cu-component--card-overlay-item');

        $cards.each(function() {
          var $card = $(this);
          var $cardBody = $card.find('.cu-component--card-overlay-item--card-body');

          if ($cardBody.length && !$card.data('card-overlay-initialized')) {
            $card.data('card-overlay-initialized', true);

            $card.hover(
              function() {
                $cardBody.slideDown(300);
              },
              function() {
                $cardBody.slideUp(300);
              }
            );
          }
        });
      });
    }
  };

})(jQuery, Drupal);
