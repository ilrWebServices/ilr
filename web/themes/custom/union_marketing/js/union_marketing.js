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

  Drupal.behaviors.union_marketing_banner_bg_fixup = {
    attach: function (context, settings) {
      const banner_images = context.querySelectorAll('.cu-banner--page .field-representative-image');

      for (const banner_image of banner_images) {
        let banner = banner_image.closest('.cu-banner--page');
        let banner_media_elem = banner.querySelector('.cu-banner__media');
        let comment = document.createComment('This image was moved here via javascript from `.field-representative-image`.');

        // Move images from the representative image field into the proper
        // container in the Union page banner component.
        banner_media_elem.appendChild(banner_image);
        banner_media_elem.appendChild(comment);

        // Add a class that can be targeted for banners with media.
        banner.classList.add('cu-banner--has-media');
      }
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
        event_registration_form.appendChild(form_overlay);

        form_overlay.addEventListener('click', function (event) {
          event_registration_form.dataset.collapsed = 0;
          event.target.style.display = 'none';

          for (const element of elements) {
            element.style.display = element.dataset.previousDisplay;
          }
        });
      }
    }
  }

  Drupal.behaviors.union_marketing_event_agenda_toggle = {
    attach: function (context, settings) {
      const agenda_components = context.querySelectorAll('.paragraph--type--agenda');

      for (const agenda_component of agenda_components) {
        agenda_component.dataset.collapsed = 1;

        agenda_component.addEventListener('click', function (event) {
          // Only toggle the collapsed attribute if the click is on the div
          // itself. This will prevent collapse when a user selects text.
          if (event.target === event.currentTarget) {
            event.currentTarget.dataset.collapsed = event.currentTarget.dataset.collapsed == 1 ? 0 : 1;
          }
        });
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

  Drupal.behaviors.union_marketing_instructor_block_observer = {
    attach: function (context, settings) {
      // If there are no active instructor-participants in .block__instructors,
      // add a class to it.
      let instructor_block = context.querySelector('.block__instructors');

      if (!instructor_block) {
        return;
      }

      let instructor_list = instructor_block.querySelector('.cu-grid--people');

      if (!instructor_list) {
        return;
      }

      const observer = new MutationObserver(function(mutationList, observer) {
        let active_instructors = instructor_list.querySelectorAll('.node--participant.active');

        if (active_instructors.length) {
          instructor_block.classList.remove('empty');
        }
        else {
          instructor_block.classList.add('empty');
        }
      });

      observer.observe(instructor_list, { attributes: true, childList: true, subtree: true });

      // Trigger a mutation on the instructor list to run it once automatically.
      instructor_list.classList.add('js-enabled');
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
    let instructor_selector = '.block__instructors .node--participant';
    let active_instructor_selector = '.block__instructors .node--participant[data-classid="' + event.detail + '"]';
    let all_instructors = document.querySelectorAll(instructor_selector);
    let active_instructors = document.querySelectorAll(active_instructor_selector);

    all_instructors.forEach(function(el) {
      el.classList.remove('active');
      el.classList.add('inactive');
    });

    active_instructors.forEach(function(el) {
      el.classList.remove('inactive');
      el.classList.add('active');
    });
  });

})(window, document, Drupal);
