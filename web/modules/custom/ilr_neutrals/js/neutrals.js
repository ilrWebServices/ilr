/**
 * @file neutrals.js
 *
 * Defines enhancements for the neutrals listing.
 */
(function (document) {

  'use strict';

  /**
   * Add a js-created filter form to the neutrals listing.
   *
   * This is done to replicate the views exposed filters, which are configured
   * to display in a block, thus hiding them from display. It also allows us to
   * display the state filter as a select list.
   */
  Drupal.behaviors.neutrals_listing_filter = {
    attach: function (context) {
      // Only run on full page requests, not ajax.
      if (context !== document) {
        return;
      }

      let params = new URLSearchParams(document.location.search);
      let neutrals_view = context.querySelector('.neutrals-view');
      let filter_form = document.createElement('form');
      let help_link = document.createElement('a');
      help_link.href = 'https://neutrals.app.ilr.cornell.edu/';
      help_link.text = Drupal.t('Add or Edit Your Neutral Information');
      help_link.classList.add('js-neutrals-filter-form-help');
      filter_form.method = 'GET';
      filter_form.classList.add('js-neutrals-filter-form');
      filter_form.appendChild(help_link);
      neutrals_view.parentNode.insertBefore(filter_form, neutrals_view);

      if (params.get('state') || params.get('arbid')) {
        let back_button = document.createElement('button');
        back_button.innerHTML = Drupal.t('Back');
        back_button.classList.add('cu-input--submit');
        filter_form.appendChild(back_button);
        neutrals_view.scrollIntoView({ behavior: "smooth" });
        return;
      }

      let states = [];
      let filter_form_state_select = document.createElement('select');
      let address_elements = context.querySelectorAll('.ilr-neutral__address');
      filter_form_state_select.name = 'state';

      address_elements.forEach(address_element => {
        states.push(address_element.dataset.state);
      });

      // Get just the unique states for options. See https://stackoverflow.com/a/9229821
      let state_opts = [...new Set(states)];
      state_opts.sort();

      let opt_any = document.createElement('option');
      opt_any.value = '';
      opt_any.text = '- State -';
      filter_form_state_select.appendChild(opt_any);

      for (const state_opt of state_opts) {
        const option = document.createElement("option");
        option.value = state_opt;
        option.text = state_opt;
        filter_form_state_select.appendChild(option);
      }

      filter_form.appendChild(filter_form_state_select);

      filter_form_state_select.addEventListener('change', function() {
        filter_form.submit();
      });

    }
  };

})(document);
