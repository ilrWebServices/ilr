(function (window, document, Drupal) {

  /**
  * woo!
  */
  Drupal.behaviors.griftCollectionMenu = {
    attach: function (context, settings) {
      let collection_menu_block = context.querySelector('.block-collection-menu-block');
      let collection_menu_placeholder = context.querySelector('[data-collection-menu-grifter]');

      if (!collection_menu_block || !collection_menu_placeholder) {
        return;
      }

      let comment = document.createComment('See the CollectionMenuGrifter paragraph behavior to understand why this block was moved here!');
      collection_menu_placeholder.appendChild(comment);
      collection_menu_placeholder.appendChild(collection_menu_block);
    }
  };

})(window, document, Drupal);
