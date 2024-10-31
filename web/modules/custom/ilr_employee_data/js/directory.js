/**
 * @file directory.js
 *
 * Defines enhancements for the employee directory.
 */
(function (document) {

  'use strict';

  /**
   * Add enhancements to the people directory.
   */
  Drupal.behaviors.directory_enhancements = {
    attach: function (context) {
      // Only run on full page requests, not ajax.
      if (context !== document) {
        return;
      }

      const urlParams = new URLSearchParams(window.location.search);
      let filter_form = context.querySelector('.employee-directory .filters');

      filter_form.addEventListener('submit', function (event) {
        // If this page was server-side filtered, prevent client filter
        // enhancements, since the dataset is incomplete.
        if (urlParams.size > 0) {
          return;
        }

        event.preventDefault();
        const personas = context.querySelectorAll('.cu-person-wrapper');
        const data = new FormData(event.target);
        const search_str = data.get('s');
        const dept_str = data.get('dept');
        const role_str = data.get('role');

        for (const persona of personas) {
          persona.style.display = 'block';
        }

        let query_selector = [];

        if (search_str) {
          query_selector.push('[data-search-index*="' + search_str.toLowerCase().trim() + '"]');
        }

        if (dept_str) {
          query_selector.push('[data-departments*="' + dept_str.trim() + '"]');
        }

        if (role_str) {
          query_selector.push('[data-role="' + role_str.trim() + '"]');
        }

        if (query_selector.length) {
          let results = context.querySelectorAll('.employee-directory ' + query_selector.join(''));

          if (results.length) {
            for (const persona of personas) {
              persona.style.display = 'none';
            }

            for (const result of results) {
              result.parentElement.style.display = 'block';
            }
          }
          else {
            alert('No people that match that search. Sorry!')
          }
        }
      });

      filter_form.querySelector('#search').focus();
    }
  };

})(document);
