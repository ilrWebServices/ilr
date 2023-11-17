(function(document) {

  'use strict';

  Drupal.behaviors.union_admin_media_enhancements = {
    attach: function (context) {
      if (Object.prototype.toString.call(context) !== '[object HTMLDivElement]') {
        return;
      }

      let mediaLibraryForm = document.querySelector('#views-exposed-form-media-library-widget');

      if (!mediaLibraryForm) {
        return false;
      }

      let mediaLibraryFilterInput = mediaLibraryForm.querySelector('[data-drupal-selector="edit-name"]');

      let dupeMessages = [...context.querySelectorAll('.messages--error li')]
        .filter(li => li.innerText.includes('This file has already been uploaded'));

      for (let dupeMessage of dupeMessages) {
        let filename = dupeMessage.querySelector('a');

        if (filename) {
          mediaLibraryFilterInput.value = filename.innerText;
          mediaLibraryForm.querySelector('.js-form-submit').click();

          // Be helpful and select the duplicate item.
          setTimeout(function() {
            let firstDupe = document.querySelector('.media-library-item input[type="checkbox"]');

            if (firstDupe) {
              firstDupe.checked = true;
              firstDupe.dispatchEvent(new Event('change'));
            }
          }, 750);
        }
      }
    }
  }

})(document);
