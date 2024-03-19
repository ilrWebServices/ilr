/**
 * @file ilr_select_list.js
 *
 * Defines the behavior of the select-list display of projects.
 */

(function (document) {

  'use strict';

  Drupal.behaviors.ilrSelectList = {
    attach: function (context) {
      // Only run on full page requests, not ajax.
      if (context !== document) {
        return;
      }

      document.addEventListener('click', function(event) {
        if (event.target.matches('.project-list__trigger')) {
          if (event.target.parentNode.matches('.select-list--expanded')) {
            event.target.parentNode.classList.remove('select-list--expanded');
            event.target.setAttribute('aria-expanded', 'false');
            event.target.parentNode.scrollTop = 0;
          }
          else {
            event.target.parentNode.classList.add('select-list--expanded');
            event.target.setAttribute('aria-expanded', 'true');
          }
        }
      }, false);

      // Enable select list collapse by hitting escape.
      document.addEventListener('keydown', function(event) {
        if (event.key === "Escape") {
          const project_triggers = document.querySelectorAll('.select-list--expanded .project-list__trigger');

          project_triggers.forEach(function(project_trigger) {
            project_trigger.click();
          });
        }
      });
    }
  };

})(document);
