// @todo Add integrity check via importmap or <link rel="modulepreload"...
import { create, insertMultiple, search } from 'https://cdn.jsdelivr.net/npm/@orama/orama@3.1.18/+esm';

(function(document) {

  'use strict';

  /**
   * Define behavior for the <ilr-program-finder> custom element.
   */
  class ilrProgramFinder extends HTMLElement {

    #db;
    #items;
    #message;

    constructor() {
      super();
      this.#message = document.createElement('div');
      this.#message.classList.add('program-finder__message');
    }

    async connectedCallback() {
      const type = this.getAttribute('type');

      this.#db = await create({
        schema: {
          title: 'string',
          summary: 'string',
          topics: 'enum[]',
          dates: 'enum[]',
          format: 'enum[]'
        }
      });

      this.#items = this.querySelectorAll('[data-component-id="ilr_program_finder:program_finder_item"]');
      const docs = [];

      this.#items.forEach((item, index) => {
        docs.push({
          id: item.dataset.facetItemId,
          title: item.querySelector('.program-finder-item__title').textContent,
          summary: item.querySelector('.program-finder-item__summary').textContent,
          topics: item.dataset.facetTopics.split("\t"),
          dates: item.dataset.facetDates.split("\t"),
          format: item.dataset.facetDeliveryMethods.split("\t"),
        });
      });

      insertMultiple(this.#db, docs);

      this.addEventListener('click', this.delegate);
      this.addEventListener('submit', this.delegate);

      // This ensures that facet state added to the URL via pushState updates the page.
      addEventListener("popstate", (event) => { this.update() });

      // Add the text search form.
      this.querySelector('.program-finder__sidebar-header').insertAdjacentHTML('beforebegin', `<form method="get">
        <input type="search" name="q" placeholder="Search" class="program-finder__search"></input>
        <button aria-label="Search" title="Search"><svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" height="24" viewBox="0 0 24 24" width="24" focusable="false" aria-hidden="true" style="pointer-events: none; display: inherit; width: 100%; height: 100%;"><path clip-rule="evenodd" d="M16.296 16.996a8 8 0 11.707-.708l3.909 3.91-.707.707-3.909-3.909zM18 11a7 7 0 00-14 0 7 7 0 1014 0z" fill-rule="evenodd"></path></svg></button>
      </form>`);

      // Initialize the search facets.
      this.update();
    }

    delegate(event) {
      const url_params = new URLSearchParams(window.location.search);

      if (event.target.matches('input[type="checkbox"]')) {
        if (event.target.checked) {
          url_params.append(event.target.name, event.target.value);
        }
        else {
          url_params.delete(event.target.name, event.target.value);
        }

        history.pushState({}, '', window.location.pathname + '?' + url_params.toString());
        this.update();
      }

      if (event.target.matches('button[data-key]')) {
        url_params.delete(event.target.dataset.key, event.target.textContent);
        history.pushState({}, '', window.location.pathname + '?' + url_params.toString());
        this.update();
      }

      if (event.target.matches('form')) {
        event.preventDefault();
        const input = event.target.firstElementChild;
        const current_search = url_params.get('q');

        if (input.value !== current_search) {
          url_params.delete(input.name);

          if (input.value) {
            url_params.append(input.name, input.value);
          }

          history.pushState({}, '', window.location.pathname + '?' + url_params.toString());
          this.update();
        }
      }
    }

    update() {
      const search_input_element = this.querySelector('.program-finder__search');
      const facet_wrapper = this.querySelector('.program-finder__facets');
      const url_params = new URLSearchParams(window.location.search);
      const enabled_facets_where = {};
      const header = this.querySelector('.program-finder__header');

      // Empty the header. Updated summary buttons will go here.
      header.replaceChildren();

      for (const [key, value] of url_params.entries()) {
        let button_element = document.createElement('button');
        button_element.classList.add('ilr-program-finder__button');
        button_element.dataset.key = key;
        button_element.textContent = value;
        header.appendChild(button_element);
      }

      // Empty the facet_wrapper. We'll replace the content with the updated facets.
      facet_wrapper.replaceChildren();

      const search_options = {
        limit: this.#items.length,
        // @todo Define these facets elsewhere? Maybe using the facet module API?
        facets: {
          "topics": {},
          "format": {},
          "dates": {},
        },
      };

      if (url_params.get('q')) {
        search_options.term = url_params.get('q');
        search_options.properties = ['title', 'summary'];
        search_options.boost = {
          title: 2,
        };
        search_input_element.value = url_params.get('q');
      }
      else {
        search_input_element.value = '';
      }

      if (url_params.getAll('topics').length) {
        enabled_facets_where.topics = { containsAll: url_params.getAll('topics') };
      }

      if (url_params.getAll('dates').length) {
        enabled_facets_where.dates = { containsAll: url_params.getAll('dates') };
      }

      if (url_params.getAll('format').length) {
        enabled_facets_where.format = { containsAll: url_params.getAll('format') };
      }

      if (enabled_facets_where) {
        search_options.where = enabled_facets_where;
      }

      const results = search(this.#db, search_options);
      // console.log(results);

      for (const [facet_name, facet] of Object.entries(results.facets)) {
        let facet_element = document.createElement('div');
        let heading_element = document.createElement('h3');
        facet_element.classList.add('ilr-program-finder__facet');
        heading_element.classList.add('ilr-program-finder__facet-heading');
        heading_element.classList.add('cu-heading');
        heading_element.textContent = this.getFacetLabel(facet_name);
        facet_element.appendChild(heading_element);

        for (const [value_name, value] of Object.entries(facet.values)) {
          let facet_item_label_element = document.createElement('label');
          let facet_item_element = document.createElement('input');
          facet_item_label_element.classList.add('cu-text');
          facet_item_element.setAttribute('type', 'checkbox');
          facet_item_element.setAttribute('name', facet_name);
          facet_item_element.setAttribute('value', value_name);

          if (url_params.has(facet_name, value_name)) {
            facet_item_element.setAttribute('checked', 'checked');
            facet_item_element.checked = true;
          }

          facet_item_label_element.appendChild(facet_item_element);
          facet_item_label_element.insertAdjacentText('beforeend', value_name);
          facet_item_label_element.insertAdjacentHTML('beforeend', `<span>${value}</span>`);
          facet_element.appendChild(facet_item_label_element);
        }

        facet_wrapper.appendChild(facet_element);
      }

      // Hide all items.
      this.#items.forEach((item, index) => {
        item.classList.add('hidden');
      });

      // Show only the hits, in search result order.
      results.hits.forEach((result_hit, result_index) => {
        this.#items.forEach((item, index) => {
          if (item.dataset.facetItemId === result_hit.id) {
            item.classList.remove('hidden');
            item.style.order = result_index;
          }
        });
      });

      if (results.count === 0) {
        this.#message.textContent = 'No results.';
        header.appendChild(this.#message);
      }
    }

    getFacetLabel(facet_name) {
      return {
        'topics': 'Topic',
        'dates': "Start Month"
      }[facet_name] ?? facet_name.charAt(0).toUpperCase() + facet_name.slice(1);
    }

  }

  customElements.define('ilr-program-finder', ilrProgramFinder);

})(document);
