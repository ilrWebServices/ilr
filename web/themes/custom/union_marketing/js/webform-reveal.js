(function(document) {

  'use strict';

  class webformReveal extends HTMLElement {
    connectedCallback() {
      let webform = this;
      let submit_text_collapsed = webform.getAttribute('submit-text-collapsed');
      let submit_button = webform.querySelector('.webform-button--submit');
      let submit_text_init = submit_button.value;

      if (submit_text_collapsed) {
        submit_button.value = submit_text_collapsed;
      }

      webform.style.display = 'block';
      webform.style.position = 'relative';
      webform.dataset.collapsed = 1;

      let overlay = document.createElement('div');
      overlay.style.position = 'absolute';
      overlay.style.inset = '0px';
      overlay.style.cursor = 'pointer';

      overlay.addEventListener('click', function (event) {
        event.target.style.display = 'none';
        webform.dataset.collapsed = 0;

        // Focus the first input element in the form.
        webform.querySelector('input').focus();

        if (submit_text_collapsed) {
          submit_button.value = submit_text_init;
        }
      });

      webform.appendChild(overlay);
    }
  }

  customElements.define("webform-reveal", webformReveal);

})(document);
