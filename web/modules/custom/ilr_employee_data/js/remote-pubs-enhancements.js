/**
 * @file remote-pubs-enhancements.js
 *
 * Defines enhancements for remote publications.
 */
(function (document) {

  'use strict';

  /**
   * Add enhancements to the remote publications admin form.
   */
  Drupal.behaviors.remote_pubs_enhancements = {
    dragStartOption: null,

    dragStart: function (e) {
      this.dragStartOption = e.currentTarget;
      e.currentTarget.style.cursor = 'grabbing';
    },

    dragOver: function (e) {
      e.preventDefault();
      this.style.borderTopColor = 'rgba(0, 0, 0, .5)';
    },

    dragLeave: function (e) {
      e.preventDefault();
      this.style.borderTopColor = 'rgba(0, 0, 0, 0)';
      this.style.cursor = 'grab';
    },

    dragDrop: function (e) {
      e.currentTarget.parentElement.insertBefore(this.dragStartOption, e.currentTarget);
      e.currentTarget.style.borderTopColor = 'rgba(0, 0, 0, 0)';
      e.currentTarget.style.cursor = 'grab';
    },

    attach: function (context) {
      let options = context.querySelectorAll('.publication-group-options .form-checkboxes .form-type-checkbox');

      if (!options) {
        return;
      }

      this.dragStart = this.dragStart.bind(this);
      this.dragDrop = this.dragDrop.bind(this);

      options.forEach(option => {
        option.addEventListener('dragstart', this.dragStart);
        option.addEventListener('dragover', this.dragOver);
        option.addEventListener('dragleave', this.dragLeave);
        option.addEventListener('drop', this.dragDrop);
        option.setAttribute('draggable', 'true');
        option.style.margin = 0;
        option.style.padding = '.33em 0';
        option.style.borderTop = '1px solid rgba(0, 0, 0, 0)';
        option.style.cursor = 'grab';
      });
    }
  };

})(document);
