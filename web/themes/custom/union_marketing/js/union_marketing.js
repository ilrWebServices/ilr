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
      document.addEventListener('click', function(event) {
        if (event.target.matches('.closeSearch')) {
          document.getElementById('ilr_search_reveal').checked = false;
        }
      });

      // For accessibility, always dismiss the search box when the escape key is
      // pressed.
      document.addEventListener('keyup', function(event) {
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
        let banner_media_elem = banner_image.closest('.cu-banner--page').querySelector('.cu-banner__media');
        let comment = document.createComment('This image was moved here via javascript from `.field-representative-image`.');

        // Move images from the representative image field into the proper
        // container in the Union page banner component.
        banner_media_elem.appendChild(banner_image);
        banner_media_elem.appendChild(comment);
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

        course_notification_form.addEventListener('click', function(event) {
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

        in_page_signup_jump.addEventListener('click', function() {
          const course_notification_form = context.querySelector('.webform-submission-course-notification-form').closest('.block-webform');

          if (course_notification_form) {
            course_notification_form.querySelector('.info-fields').style.display = 'block';
            course_notification_form.classList.add('js-modal');
          }
        });

        // If there are any `.js-modal`s on the page, disable them if the click
        // was outside of one.
        document.addEventListener('click', function(event) {
          if (event.target.matches('.in-page-signup-jump') || event.target.closest('.js-modal')) {
            return;
          }

          document.querySelector('.js-modal').classList.remove('js-modal');
        });

        document.onkeydown = function(event) {
          if ('key' in event && event.key.substring(0, 3) === 'Esc') {
            document.querySelector('.js-modal').classList.remove('js-modal');
          }
        };
      }
    }
  };

  Drupal.behaviors.union_marketing_registration_form = {
    attach: function (context, settings) {
      const registrationForms = context.querySelectorAll('.cu-registration-form');

      if (!registrationForms) {
        return;
      }

      context.addEventListener('change', function(event) {
        if (event.target.matches('.cu-checkbutton__input')) {
          let registerButton = event.target.closest('.cu-registration-form').querySelector('.cu-js-register-link');

          if (registerButton) {
            registerButton.setAttribute('href', event.target.value);
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

  // Temporary section navigation behavior.
  // @todo: Remove this once the section navigation strategy has been decided.
  Drupal.behaviors.union_marketing_section_navigation = {
    attach: function (context, settings) {
      const currentStudentNav = context.querySelector('#block-currentstudentsnavigation');
      // The `is-active` class from core is also added via js. Since the
      // querySelector wasn't working, we'll just use the pathname.
      const currentPath = window.location.pathname;
      let activeItem = currentStudentNav.querySelector('a[href="' + currentPath + '"');

      if (!activeItem) {
        return;
      }

      activeItem.closest('ul').classList.add("active");
      activeItem.closest('ul').closest('li').classList.add("active");
    }
  };

})(window, document, Drupal);
