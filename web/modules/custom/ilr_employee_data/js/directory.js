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

      let filter_form = context.querySelector('.employee-directory .filters');

      filter_form.addEventListener('submit', function (event) {
        event.preventDefault();
        const personas = context.querySelectorAll('.cu-person-wrapper');
        const data = new FormData(event.target);
        const search_str = data.get('search');
        const dept_str = data.get('dept');

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

      const search_box = document.createElement('div');
      const search_box_label = document.createElement('label');
      const search_box_input = document.createElement('input');

      search_box.classList.add('search-filter', 'form-item', 'cu-input-list__item', 'has-float-label');

      search_box_label.innerText ='Search for people';
      search_box_label.setAttribute('for', 'search');
      search_box_label.classList.add('cu-label');

      search_box_input.setAttribute('id', 'search');
      search_box_input.setAttribute('name', 'search');
      search_box_input.classList.add('form-text', 'cu-input', 'cu-input--text');

      search_box.appendChild(search_box_label);
      search_box.appendChild(search_box_input);

      filter_form.appendChild(search_box);
      search_box_input.focus();
    }
  };

})(document);
