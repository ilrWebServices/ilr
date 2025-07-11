// Miscellaneous javascript functions for this custom theme.
(function (window, document, Drupal) {

  Drupal.behaviors.union_marketing_search_toggle = {
    attach: function (context, settings) {
      if (context !== document) {
        return;
      }

      // The search box is normally toggled via an invisible checkbox and some
      // CSS. This is here to trigger that checkbox from the close button within
      // the search form.
      document.addEventListener('click', function (event) {
        if (event.target.matches('.closeSearch')) {
          document.getElementById('ilr_search_reveal').checked = false;
        }
      });

      // For accessibility, always dismiss the search box when the escape key is
      // pressed.
      document.addEventListener('keyup', function (event) {
        // The escape key maps to keycode `27`.
        if (event.keyCode == 27) {
          document.getElementById('ilr_search_reveal').checked = false;
        }
      });
    }
  };

  Drupal.behaviors.union_marketing_course_notification_form = {
    attach: function (context, settings) {
      const course_notification_forms = context.querySelectorAll('.webform-submission-course-notification-form, .webform-submission-course-customization-request-form');

      for (const course_notification_form of course_notification_forms) {
        let inputs = course_notification_form.querySelector('.info-fields');

        // Hide most of the form inputs by default.
        inputs.style.display = 'none';

        course_notification_form.addEventListener('click', function (event) {
          if (event.target.matches('.webform-button--submit' === false)) {
            return;
          }

          // `currentTarget` is the form, where this event handler is attached.
          let inputs = event.currentTarget.querySelector('.info-fields');

          // If the inputs are hidden, show them and don't submit the form. From
          // now on, when the submit button is clicked the form will be actually
          // submitted.
          if (inputs.style.display === 'none') {
            inputs.style.display = 'block';
            event.preventDefault();
          }
        });
      }

      const in_page_signup_jump = context.querySelector('.in-page-signup-jump');

      if (in_page_signup_jump) {
        in_page_signup_jump.classList.add('js')

        in_page_signup_jump.addEventListener('click', function () {
          const course_notification_form = context.querySelector('.webform-submission-course-notification-form').closest('.block-webform');

          if (course_notification_form) {
            course_notification_form.querySelector('.info-fields').style.display = 'block';
            course_notification_form.classList.add('js-modal');
          }
        });

        // If there are any `.js-modal`s on the page, disable them if the click
        // was outside of one.
        document.addEventListener('click', function (event) {
          if (event.target.matches('.in-page-signup-jump') || event.target.closest('.js-modal')) {
            return;
          }

          document.querySelector('.js-modal').classList.remove('js-modal');
        });

        document.onkeydown = function (event) {
          if ('key' in event && event.key.substring(0, 3) === 'Esc') {
            document.querySelector('.js-modal').classList.remove('js-modal');
          }
        };
      }
    }
  };

  Drupal.behaviors.union_marketing_generic_event_registration_form = {
    attach: function (context, settings) {

      const event_registration_forms = context.querySelectorAll('.block-field-block--node--event-landing-page--field-registration-form');

      for (const event_registration_form of event_registration_forms) {
        let elements = event_registration_form.querySelectorAll('.form-item:not(.webform-actions, .post-button-text), .fieldgroup');

        // Hide most of the form inputs by default.
        for (const element of elements) {
          element.dataset.previousDisplay = element.style.display;
          element.style.display = 'none';
        }

        event_registration_form.dataset.collapsed = 1;

        let form_overlay = document.createElement('div');
        form_overlay.style.position = 'absolute';
        form_overlay.style.inset = '0px';
        form_overlay.style.cursor = 'pointer';
        form_overlay.addEventListener('click', function (event) {
          event_registration_form.dataset.collapsed = 0;
          event.target.style.display = 'none';
          event_registration_form.scrollIntoView({ behavior: "smooth" })

          for (const element of elements) {
            element.style.display = element.dataset.previousDisplay;
          }
        });
        event_registration_form.appendChild(form_overlay);

        let form_close = document.createElement('button');
        form_close.setAttribute('title', 'Close');
        form_close.classList.add('registration-form-collapse');
        form_close.innerHTML = '<span>Close</span>';
        form_close.addEventListener('click', function (event) {
          for (const element of elements) {
            element.style.display = 'none';
          }
          event_registration_form.dataset.collapsed = 1;
          form_overlay.style.display = 'block';
        });
        event_registration_form.appendChild(form_close);
      }
    }
  }

  Drupal.behaviors.union_marketing_generic_event_registration_form_link_trigger = {
    attach: function (context, settings) {
      if (context !== document) {
        return;
      }

      // If the event location link is a fragment to `#register`, trigger a
      // click on the registration form overlay to open it.
      document.addEventListener('click', function(event) {
        if (event.target.matches('.field--location-link a[href="#register"]')) {
          event.preventDefault();

          // See the `form_overlay` created in
          // Drupal.behaviors.union_marketing_generic_event_registration_form().
          const event_registration_form_overlay = document.querySelector('.block-field-block--node--event-landing-page--field-registration-form > div:last-of-type');

          if (event_registration_form_overlay) {
            event_registration_form_overlay.click();
          }
        }
      });
    }
  }

  Drupal.behaviors.union_marketing_landing_page_contact_form = {
    attach: function (context, settings) {

      const forms = context.querySelectorAll('.block-field-block--node--landing-page--field-form');

      for (const form of forms) {
        form.dataset.state = 'initial';

        let form_overlay = document.createElement('div');
        form_overlay.classList.add('form-overlay');
        form_overlay.addEventListener('click', function (event) {
          // Focus on the first input.
          form.querySelector('.cu-input').focus();
          form.dataset.state = 'activated';
        });
        form.appendChild(form_overlay);

        let form_close = document.createElement('button');
        form_close.classList.add('form-collapse');
        form_close.setAttribute('title', 'Close');
        form_close.innerHTML = '<span>Close</span>';
        form_close.addEventListener('click', function (event) {
          form.dataset.state = 'initial';
        });
        form.appendChild(form_close);
      }
    }
  }

  Drupal.behaviors.union_marketing_cahrs_newsletter_option_trigger = {
    attach: function (context, settings) {
      const cahrs_newsletter_form = context.querySelector('.webform--cahrs-newsletter-signup');

      if (!cahrs_newsletter_form) {
        return;
      }

      let options_elements = cahrs_newsletter_form.querySelectorAll('#edit-cahrs-newsletters--wrapper .form-item.cu-input-list__item--checkbox');

      if (options_elements.length === 0) {
        return;
      }

      let cahrs_newsletter_options_array = Array.from(options_elements);
      let cahrs_quarterly_options = cahrs_newsletter_options_array.slice(2); // There are two options that are not CAHRS Quarterly.

      for (const element of cahrs_quarterly_options) {
        element.classList.add('visually-hidden');
      }

      let messageText = document.createTextNode(Drupal.t('CAHRS publishes quarterly summaries focusing on select topics in human resources. Please select the topics you would like to receive.'));
      let message = document.createElement('div');
      let quarterlyToggleLabel = document.createElement('label');
      let labelText = document.createTextNode(Drupal.t('CAHRS Quarterly'));
      let quarterlyToggle = document.createElement('input');
      let firstItemStyle = window.getComputedStyle(cahrs_newsletter_options_array[1]);

      message.appendChild(messageText);
      message.classList.add('visually-hidden', 'cahrs-quarterly-message');
      message.style.margin = '1em'; // This gives it a top and bottom margin. The left margin will be overridden.
      message.style.marginLeft = firstItemStyle.marginLeft;
      quarterlyToggle.setAttribute('type','checkbox');
      quarterlyToggle.style.marginRight = '10px'; // Add space between the checkbox and the label.
      quarterlyToggleLabel.style.margin = firstItemStyle.margin;
      quarterlyToggleLabel.appendChild(quarterlyToggle);
      quarterlyToggleLabel.appendChild(labelText);
      quarterlyToggleLabel.classList.add('cu-input-list__item--checkbox');
      cahrs_newsletter_options_array[1].parentNode.insertBefore(quarterlyToggleLabel, cahrs_newsletter_options_array[2]);
      cahrs_newsletter_options_array[1].parentNode.insertBefore(message, cahrs_newsletter_options_array[2]);

      quarterlyToggle.addEventListener('click', function(event) {
        for (const element of cahrs_quarterly_options) {
          if (event.target.checked) {
            element.classList.remove('visually-hidden');
            message.classList.remove('visually-hidden');
          }
          else {
            element.classList.add('visually-hidden');
            message.classList.add('visually-hidden');
          }

        }
      });
    }
  }

  Drupal.behaviors.union_marketing_event_agenda_toggle = {
    attach: function (context, settings) {
      // Set only the first agenda item to open.
      const agenda_component = context.querySelector('.paragraph--type--agenda');

      if (agenda_component) {
        agenda_component.setAttribute('open', '');
      }
    }
  }

  Drupal.behaviors.union_marketing_registration_form = {
    attach: function (context, settings) {
      const registrationForms = context.querySelectorAll('.cu-registration-form');

      if (!registrationForms) {
        return;
      }

      context.addEventListener('change', function (event) {
        if (event.target.matches('.cu-checkbutton__input')) {
          let registerButton = event.target.closest('.cu-registration-form').querySelector('.cu-js-register-link');
          let class_select_event = new CustomEvent('registration-form-class-select', { detail: event.target.dataset.classid });
          document.dispatchEvent(class_select_event);

          if (registerButton) {
            if (event.target.value && !['full', 'cancelled', 'registrationClosed'].some(str => str in event.target.dataset)) {
              registerButton.setAttribute('href', event.target.value);
            }
            else {
              registerButton.removeAttribute('href');
            }
          }
        }
      }, false);

      for (const registrationForm of registrationForms) {
        const buttons = registrationForm.querySelectorAll('.cu-button');

        if (buttons.length === 0) {
          continue;
        }

        const input_name = registrationForm.querySelector('.cu-checkbutton__input').getAttribute('name');

        let registerButton = document.createElement('a');
        registerButton.setAttribute('class', 'cu-button cu-button--alt cu-js-register-link');
        registerButton.innerHTML = 'Register';
        registrationForm.querySelector('.cu-registration-form__form').appendChild(registerButton);

        // Hide any checkbutton registration links.
        buttons.forEach(function (button) {
          button.style.display = 'none';
        });

        let available_inputs = registrationForm.querySelectorAll('input[name="' + input_name + '"]:not([disabled])');
        let checked_input = registrationForm.querySelector('input[name="' + input_name + '"]:checked');

        if (checked_input) {
          checked_input.checked = true;
          checked_input.dispatchEvent(new Event('change', {bubbles: true}));
        }
        else if (available_inputs.length === 1) {
          available_inputs[0].checked = true;
          available_inputs[0].dispatchEvent(new Event('change', {bubbles: true}));
        }
      }
    }
  };

  Drupal.behaviors.union_marketing_dialog_closer = {
    attach: function (context, settings) {
      if (context !== document) {
        return;
      }

      document.addEventListener('click', (e) => {
        if (e.target.classList.contains('ui-widget-overlay')) {
          const dialogs = context.querySelectorAll('.ui-dialog');

          for (let dialog of dialogs) {
            dialog.querySelector('.ui-dialog-titlebar-close').click();
          }
        }
      });
    }
  };

  document.addEventListener('registration-form-class-select', function (event) {
    let instructor_selector = '.block__instructors .cu-person';
    let active_instructor_selector = '.block__instructors .cu-person[data-classid~="' + event.detail + '"]';

    let all_instructors = document.querySelectorAll(instructor_selector);
    let active_instructors = document.querySelectorAll(active_instructor_selector);

    all_instructors.forEach(function(el) {
      el.classList.add('inactive');
    });

    active_instructors.forEach(function(el) {
      el.classList.remove('inactive');
    });
  });

  // Close all facet details elements on small screens.
  Drupal.behaviors.union_marketing_responsive_details_element = {
    attach: function (context, settings) {
      if (context !== document) {
        return;
      }

      // See https://stackoverflow.com/a/49419028.
      let breakpoint = getComputedStyle(document.documentElement, null).getPropertyValue('--breakpoint');

      // Abort on wider screens (medium breakpoint).
      if (['md', 'lg'].includes(breakpoint)) {
        return;
      }

      const details = document.querySelectorAll("details");

      details.forEach(function(detail) {
        if (detail.classList.contains('block-facets')) {
          detail.removeAttribute('open');
        }
      });
    }
  };

})(window, document, Drupal);
