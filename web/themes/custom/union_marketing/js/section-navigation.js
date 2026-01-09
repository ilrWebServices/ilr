/**
 * @file
 * Section navigation select dropdown handler for Report Summary pages.
 */

(function (Drupal) {
  'use strict';

  Drupal.behaviors.ilrSectionNavigation = {
    attach: function (context, settings) {
      const selects = context.querySelectorAll('[data-ilr-section-nav] .ilr-section-nav__select');
      
      selects.forEach(function (select) {
        if (select.dataset.ilrSectionNavAttached) {
          return;
        }
        
        select.dataset.ilrSectionNavAttached = 'true';
        
        select.addEventListener('change', function (e) {
          const url = e.target.value;
          
          if (url && url !== '') {
            window.location.href = url;
          }
        });
      });
    }
  };

})(Drupal);
