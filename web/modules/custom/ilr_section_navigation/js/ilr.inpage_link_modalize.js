(function (document) {

  document.addEventListener('DOMContentLoaded', (event) => {
    let main = document.querySelector('main');

    main.addEventListener('click', (event) => {
      if (event.target.hasAttribute('href')) {
        const href = event.target.getAttribute('href');

        if (!href.startsWith('#')) {
          return;
        }

        // Find an element with the id of the hash and the modalize data element.
        const modal_target = document.querySelector(href + '[data-modalize]');

        if (!modal_target) {
          return;
        }

        event.preventDefault();

        modal_target.classList.add('modalized');
        modal_target.setAttribute('popover', 'auto');
        modal_target.showPopover();
        modal_target.addEventListener("toggle", (event) => {
          if (event.newState === "closed") {
            console.log("Popover has been removed");
            modal_target.removeAttribute('popover');
          }
        })
      }
    });
  });

})(document);
