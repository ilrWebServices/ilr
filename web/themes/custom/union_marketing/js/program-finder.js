(function ($, Drupal, once) {
  'use strict';

  // sync active month options → date_min / date_max hidden inputs
  function updateDateInputs($form) {
    var min = null, max = null;

    $form.find('.program-finder-month-option.is-active').each(function () {
      var first = $(this).data('first');
      var last = $(this).data('last');
      if (!min || first < min) min = first;
      if (!max || last > max) max = last;
    });

    $form.find('.program-finder-date-min').val(min || '');
    $form.find('.program-finder-date-max').val(max || '');
  }

  // topics write as CSV so Views AJAX doesn't choke on array inputs
  function updateTopicInput($form) {
    var ids = [];
    $form.find('.program-finder-topic-option.is-active').each(function () {
      ids.push($(this).data('value'));
    });
    $form.find('.program-finder-topic-ids').val(ids.join(','));
  }

  function updateFormatInput($form) {
    var $active = $form.find('.program-finder-format-option.is-active');
    $form.find('.program-finder-format-input').val($active.length ? $active.data('value') : '');
  }

  function toggleMulti($opt) {
    var nowActive = !$opt.hasClass('is-active');
    $opt.toggleClass('is-active', nowActive).attr('aria-checked', nowActive ? 'true' : 'false');
  }

  // format is single-select, so deactivate siblings first
  function toggleSingle($opt) {
    var $siblings = $opt.parent().find('.program-finder__filter-option');
    var wasActive = $opt.hasClass('is-active');
    $siblings.removeClass('is-active').attr('aria-checked', 'false');
    if (!wasActive) {
      $opt.addClass('is-active').attr('aria-checked', 'true');
    }
  }

  // strip the count badge before using as a pill label
  function optLabel($opt) {
    var $clone = $opt.clone();
    $clone.find('.program-finder__filter-option__count').remove();
    return $.trim($clone.text());
  }

  function makePill(type, value, label) {
    var $btn = $('<button type="button" class="program-finder__filter-pill"></button>');
    $btn.attr({ 'data-filter-type': type, 'data-filter-value': value });
    $btn.html('<span class="program-finder__filter-pill-x" aria-hidden="true">&times;</span> ' + $('<span>').text(label).html());
    return $btn;
  }

  function renderSelectedFilters() {
    var $container = $('.program-finder__selected-filters');
    if (!$container.length) return;
    $container.empty();

    var $form = $('.program-finder__filters').find('form[id*="views-exposed-form-programs"]');
    var titleVal = $.trim($form.find('input[name="title"]').val() || '');

    if (titleVal) {
      $container.append(makePill('title', titleVal, titleVal));
    }

    $form.find('.program-finder-topic-option.is-active').each(function () {
      var $opt = $(this);
      $container.append(makePill('topic', $opt.data('value'), optLabel($opt)));
    });

    var $fmt = $form.find('.program-finder-format-option.is-active');
    if ($fmt.length) {
      $container.append(makePill('format', $fmt.data('value'), optLabel($fmt)));
    }

    $form.find('.program-finder-month-option.is-active').each(function () {
      var $opt = $(this);
      $container.append(makePill('month', $opt.data('month'), optLabel($opt)));
    });
  }

  // Hero search bar — bound once at document level so AJAX replacements don't break it
  Drupal.behaviors.programFinderHeroSearch = {
    attach: function (context) {
      if (Drupal.behaviors.programFinderHeroSearch._bound) return;
      Drupal.behaviors.programFinderHeroSearch._bound = true;

      var debounceTimer;

      function getHeroInput() {
        return $('.program-finder-hero-title-input');
      }

      function getSidebarForm() {
        return $('.program-finder__filters').find('form[id*="views-exposed-form-programs"]');
      }

      function submitSearch() {
        var $form = getSidebarForm();
        if (!$form.length) return;

        var titleInput = $form.find('input[name="title"]')[0];
        if (!titleInput) return;

        // keep both inputs in sync before triggering
        titleInput.value = getHeroInput().val();

        // Views AJAX is bound via jQuery .on('click'), so .trigger() is needed here
        $form.find('input[type=submit], button[type=submit]')
          .not('[data-drupal-selector=edit-reset]')
          .trigger('click');
      }

      // seed the hero input on page load if there's a title param in the URL
      var params = new URLSearchParams(window.location.search);
      getHeroInput().val(params.get('title') || '');

      // re-sync after every AJAX response so the input doesn't blank out
      $(document).on('ajaxSuccess', function () {
        getHeroInput().val(getSidebarForm().find('input[name="title"]').val() || '');
        renderSelectedFilters();
      });

      $(document).on('input', '.program-finder-hero-title-input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(submitSearch, 500);
      });

      $(document).on('keydown', '.program-finder-hero-title-input', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        clearTimeout(debounceTimer);
        submitSearch();
      });

      $(document).on('click', '.program-finder-hero-submit', function () {
        clearTimeout(debounceTimer);
        submitSearch();
      });

      // pill × buttons
      $(document).on('click', '.program-finder__filter-pill', function () {
        var type = $(this).data('filter-type');
        var value = $(this).data('filter-value');
        var $form = getSidebarForm();

        if (type === 'title') {
          getHeroInput().val('');
          $form.find('input[name="title"]').val('');
        } else if (type === 'topic') {
          $form.find('.program-finder-topic-option[data-value="' + value + '"]').removeClass('is-active').attr('aria-checked', 'false');
          updateTopicInput($form);
        } else if (type === 'format') {
          $form.find('.program-finder-format-option.is-active').removeClass('is-active').attr('aria-checked', 'false');
          updateFormatInput($form);
        } else if (type === 'month') {
          $form.find('.program-finder-month-option[data-month="' + value + '"]').removeClass('is-active').attr('aria-checked', 'false');
          updateDateInputs($form);
        }

        renderSelectedFilters();
        $form.find('input[type=submit], button[type=submit]')
          .not('[data-drupal-selector=edit-reset]')
          .trigger('click');
      });
    }
  };

  Drupal.behaviors.programFinderFilters = {
    attach: function (context) {
      once('program-finder-filters', 'form[id*="views-exposed-form-programs"]', context).forEach(function (form) {
        var $form = $(form);

        $form.on('click keydown', '.program-finder__filter-option', function (e) {
          if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
          e.preventDefault();

          var $opt = $(this);

          if ($opt.hasClass('program-finder-topic-option')) {
            toggleMulti($opt);
            updateTopicInput($form);
          } else if ($opt.hasClass('program-finder-format-option')) {
            toggleSingle($opt);
            updateFormatInput($form);
          } else if ($opt.hasClass('program-finder-month-option')) {
            toggleMulti($opt);
            updateDateInputs($form);
          }

          renderSelectedFilters();
          $form.find('input[type=submit], button[type=submit]')
            .not('[data-drupal-selector=edit-reset]')
            .trigger('click');
        });

        // sync hidden inputs with whatever state the server rendered (e.g. on page load with params)
        updateTopicInput($form);
        updateFormatInput($form);
        updateDateInputs($form);
        renderSelectedFilters();
      });
    }
  };

})(jQuery, Drupal, once);
