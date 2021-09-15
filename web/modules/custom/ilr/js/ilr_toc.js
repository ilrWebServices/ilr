/**
 * @file ilr_toc.js
 *
 * Defines the behavior of the table of contents component.
 */

 (function (window, document) {

  'use strict';

  /**
   * Build the TOC element.
   */
  Drupal.behaviors.ilrTableOfContents = {
    attach: function (context) {
      // Only run on full page requests, not ajax.
      if (context !== document) {
        return;
      }

      let tocWrapper = context.querySelector('.cu-component--table-of-contents');
      let inPageNav = context.querySelector('.block-extra-field-block--node--page--extra-field-ilr-section-navigation');

      if (!inPageNav) {
        tocWrapper.remove();
        return;
      }

      tocWrapper.appendChild(inPageNav);
      let framedHeadings = context.querySelectorAll('.cu-section-heading--framed');

      for (let framedHeading of framedHeadings) {
        framedHeading.classList.remove('cu-section-heading--framed');
        framedHeading.classList.add('cu-section-heading--toc');
      }
    }
  };

})(window, document);
