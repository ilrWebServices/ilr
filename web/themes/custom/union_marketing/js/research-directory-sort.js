(function (Drupal) {
  Drupal.behaviors.researchDirectorySort = {
    attach: function (context) {
      var select = context.querySelector && context.querySelector('#research-directory-sort');
      if (!select || select.dataset.sortAttached) {
        return;
      }
      select.dataset.sortAttached = '1';

      // Set the select value from the URL
      // Since the template may render before query params are available to Twig.
      var params = new URLSearchParams(window.location.search);
      var currentSort = params.get('sort_order') || 'DESC';
      select.value = currentSort;

      select.addEventListener('change', function () {
        var url = new URL(window.location.href);
        url.searchParams.set('sort_order', this.value);
        window.location.href = url.toString();
      });
    }
  };
})(Drupal);
