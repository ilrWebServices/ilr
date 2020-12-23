(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.union_admin_media_enhancements = {
    attach: function (context) {
      let dupeMessages = $(context).find('.messages--error li:contains("This file has already been uploaded")');

      dupeMessages.each(function (key, value) {
        let helpText = $(value).text();
        let mediaName = helpText.match(/"(.*?)"/)[1];
        let message = $(this);

        if (!mediaName.length) {
          return false;
        }

        let mediaLibraryForm = $(document).find('#views-exposed-form-media-library-widget');

        if (!mediaLibraryForm) {
          return false;
        }

        let mediaLibraryFilterInput = mediaLibraryForm.find('[data-drupal-selector="edit-name"]');

        $(mediaLibraryFilterInput).val(mediaName);
        $(mediaLibraryForm).find('.js-form-submit').click();

        // Be helpful and select the duplicate item.
        setTimeout(function () {
          let firstDupe = $('.media-library-item').first();

          if (firstDupe) {
            firstDupe.find('input[type="checkbox"]').attr('checked', 'checked').trigger('change');
            message.append(Drupal.t(' An existing item from the media library has been selected below.'));
          }
        }, 750);

        return false;
      });
    }
  }

})(jQuery, Drupal);
