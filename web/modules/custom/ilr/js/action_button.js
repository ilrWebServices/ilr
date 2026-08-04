(function(document) {

  'use strict';

  /**
   * Define behavior for the <ilr-action-button> custom element.
   */
  class ilrActionButton extends HTMLElement {

    constructor() {
      super();
    }

    connectedCallback() {
      const type = this.getAttribute('type');
      let button_text = 'More information';

      if (type === 'node.landing_page') {
        const form = document.querySelector('.block-field-block--node--landing-page--field-form');

        if (form) {
          const form_title = form.querySelector('.webform-section-title');

          if (form_title) {
            button_text = form_title.innerText;
          }

          const button_text_el = document.createTextNode(button_text);
          this.append(button_text_el);
          this.classList.add('active');
        }

        this.addEventListener('click', (event) => {
          this.scrollToLandingPageForm(event, form);
        });
      }
    }

    scrollToLandingPageForm(event, form) {
      const first_element = form.querySelector('input:not([type="hidden"])');

      if (first_element) {
        first_element.focus();
      }

      form.scrollIntoView({ behavior: "smooth" });
    }

  }

  customElements.define("ilr-action-button", ilrActionButton);

})(document);
