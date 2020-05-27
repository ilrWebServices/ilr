// The ILR Effects main script.
(function (window, document) {
  let onScreenObserver = new IntersectionObserver((entries, onScreenObserver) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add('js--in-frame');
      }
      else {
        entry.target.classList.remove('js--in-frame');
      }
    });
  });

  document.addEventListener('DOMContentLoaded', (event) => {
    let imageTargets = document.querySelectorAll('.ilr-effect-image');
    imageTargets.forEach(function(imageTarget) {
      onScreenObserver.observe(imageTarget);

      // Add a class so we can better target opacity in css.
      imageTarget.classList.add('js-observed');

      if (imageTarget.classList.contains('curtain-reveal')) {
        let cover = document.createElement('div');
        cover.setAttribute('class', 'js-image-cover');
        imageTarget.insertAdjacentElement('afterend', cover);
      }
    });

    let shoutTargets = document.querySelectorAll('.shout');
    shoutTargets.forEach(function(shoutTarget) {
      onScreenObserver.observe(shoutTarget);
      // Add a class for progressive enhancement
      shoutTarget.classList.add('js-observed');
    });
  });

})(window, document);
