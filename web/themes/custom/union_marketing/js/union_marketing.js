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
      const banner_images = context.querySelectorAll('.cu-banner--page .field-representative-image .cu-image');

      for (const banner_image of banner_images) {
        let banner_media_elem = banner_image.closest('.cu-banner--page').querySelector('.cu-banner__media');
        let comment = document.createComment('This image was moved here via javascript from `.field-representative-image .cu-image`.');

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
    }
  };

  Drupal.behaviors.union_marketing_registration_form = {
    attach: function (context, settings) {
      const processRegistrationForms = function () {
        const registrationForms = document.querySelectorAll('.cu-registration-form');

        if (registrationForms == null) {
          return;
        }

        for (const registrationForm of registrationForms) {
          const prices = registrationForm.querySelectorAll('.cu-js-price');
          const buttons = registrationForm.querySelectorAll('.cu-button');
          const input_name = registrationForm.querySelector('.cu-checkbutton__input').getAttribute('name');

          // Hide any displayed prices.
          prices.forEach(function (price) {
            price.style.display = 'none';
          });

          // Hide any checkbutton registration links.
          buttons.forEach(function (button) {
            button.style.display = 'none';
          });

          let first_input = registrationForm.querySelector('input[name="' + input_name + '"]:not([disabled])')
          let checked_input = registrationForm.querySelector('input[name="' + input_name + '"]:checked');

          // If there is a checked input, activate its price.
          if (checked_input) {
            setActivePrice(registrationForm, checked_input);
            addRegistrationButton(registrationForm, checked_input);
          }
          // Otherwise, activate the price of the first input.
          else {
            first_input.checked = true;
            setActivePrice(registrationForm, first_input);
            addRegistrationButton(registrationForm, first_input);
          }
        }
      }

      const checkButtonChangeHandler = function (checkbutton_input) {
        let registrationForm = checkbutton_input.closest('form');
        setActivePrice(registrationForm, checkbutton_input);
        setRegisterLink(registrationForm, checkbutton_input);
      }

      const setActivePrice = function (registrationForm, input) {
        let priceElement = registrationForm.querySelector('.cu-registration-form__active-price');
        priceElement.textContent = input.dataset.price;
        activePriceSet = true;
      }

      const addRegistrationButton = function (registrationForm, checked_input) {
        let registerButton = document.createElement('a');
        registerButton.setAttribute('class', 'cu-button cu-button--alt cu-js-register-link');
        registerButton.setAttribute('href', checked_input.value);
        registerButton.innerHTML = 'Register';
        checked_input.parentNode.parentNode.appendChild(registerButton, registrationForm);
      }

      const setRegisterLink = function (registrationForm, checked_input) {
        let registerButton = registrationForm.querySelector('.cu-js-register-link');
        registerButton.setAttribute('href', checked_input.value);
      }

      window.addEventListener('DOMContentLoaded', processRegistrationForms);

      document.addEventListener('change', function (event) {
        if (event.target.matches('.cu-checkbutton__input')) {
          checkButtonChangeHandler(event.target);
        }
      }, false);
    }
  };

})(window, document, Drupal);
