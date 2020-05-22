// The ILR Effects main script.
(function (window, document) {

  let observer = new IntersectionObserver((entries, observer) => {
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
    let targets = document.querySelectorAll('.ilr-effect-image');
    targets.forEach(observer.observe.bind(observer));
  });

})(window, document);
