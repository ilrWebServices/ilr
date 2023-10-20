/**
 * @file registration_webform_widget_enhancement.js
 *
 * Defines enhancements for the event registration webform reference widget.
 */
(function (window, document) {

  'use strict';

  /**
   * Allow editors to set custom config for event registration forms.
   */
  Drupal.behaviors.enhance_widget = {
    attach: function (context) {
      let widget_wrapper = context.querySelector('.cahrs-session-details');

      if (!widget_wrapper) {
        return;
      }

      // @todo Traverse the DOM to find this in relation to the widget_wrapper.
      let default_data_textarea = context.querySelector('.form-item-field-registration-form-0-settings-default-data textarea');
      let config_line = default_data_textarea.value.match(/^# CONFIG \(DO NOT DELETE\).*\n?/gm);

      if (config_line) {
        let config = JSON.parse(config_line.pop().replace(/^# CONFIG \(DO NOT DELETE\)/, '').trim());

        for (const option of config.cahrs_session_detail_options) {
          let option_input = widget_wrapper.querySelector('input[value="' + option + '"]');
          option_input.checked = true;
        }
      }

      widget_wrapper.addEventListener('click', function(event) {
        // Is the target an input?
        if (event.target.matches('input') === false) {
          return;
        }

        let checked_options = widget_wrapper.querySelectorAll('input:checked');
        let data = {
          cahrs_session_detail_options: []
        };

        for (const checked_option of checked_options) {
          data.cahrs_session_detail_options.push(checked_option.value);
        }

        default_data_textarea.value = default_data_textarea.value.replace(/^# CONFIG \(DO NOT DELETE\).*\n?/gm, '').trim();
        default_data_textarea.value = default_data_textarea.value + "\n# CONFIG (DO NOT DELETE) " + JSON.stringify(data);

        // This will update the CodeMirror editor, too, after the hidden textarea has been modified.
        default_data_textarea.dispatchEvent(new Event('change'));
      });
    }
  };

  /**
   * Allow visitors to select options from the custom config on event registration forms.
   */
  Drupal.behaviors.enhance_form = {
    attach: function (context, settings) {
      if (settings.event_registration_form_config && settings.event_registration_form_config.cahrs_session_detail_options) {
        let session_details_options = context.querySelector('.cahrs-session-details-element');

        // Ensure that the form should have selectable options.
        if (settings.event_registration_form_config.cahrs_session_detail_options.length == 0) {
          return session_details_options.parentNode.removeChild(session_details_options);
        }

        // Remove non-configured options.
        for (const option of session_details_options.querySelectorAll('input')) {
          if (!settings.event_registration_form_config.cahrs_session_detail_options.includes(option.value)) {
            option.parentNode.parentNode.removeChild(option.parentNode);
          }
        }
      }
    }
  }

  /**
   * Hide eventid (e.g. How will you be attending?) if there's only one option.
   */
  Drupal.behaviors.enhance_form_eventid = {
    attach: function (context, settings) {
      let eventid_wrappers = context.querySelectorAll('.salesforce-event-options--wrapper');
      console.log(eventid_wrappers)

      for (const eventid_wrapper of eventid_wrappers) {
        let options = eventid_wrapper.querySelectorAll('input[type="radio"]');

        if (options.length === 1) {
          options[0].checked = true;
          eventid_wrapper.classList.add('visually-hidden');
        }
      }
    }
  }

})(window, document);
