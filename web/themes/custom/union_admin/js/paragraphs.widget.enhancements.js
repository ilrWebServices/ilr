(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.union_admin_paragraph_widget = {
    attach: function (context) {
      let closed_paragraph_element = $(context).find('.js-paragraph-mode--closed');
      closed_paragraph_element.closest('tr.draggable').addClass('closed');
    }
  }

})(jQuery, Drupal);
