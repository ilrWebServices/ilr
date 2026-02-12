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
    let imageTargets = document.querySelectorAll('.ilr-effect-image.zoom-out');
    imageTargets.forEach(function (imageTarget) {
      onScreenObserver.observe(imageTarget);
      // Add a class so we can better target opacity in css.
      imageTarget.classList.add('js-observed');
    });
  });

})(window, document);
