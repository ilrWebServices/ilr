// Miscellaneous javascript functions for this custom theme.
(function (window, document, Drupal) {

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

})(window, document, Drupal);
