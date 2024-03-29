(function (window, document, Drupal) {

  Drupal.behaviors.union_marketing_repsonsive_tables = {
    attach: function (context, settings) {
      context.querySelectorAll('.paragraph--type--rich-text table').forEach(table => {
        let headers = [];

        // @todo Support multi row headers in <thead> by combining rows and colspans.
        table.querySelectorAll('tr:first-of-type th').forEach(cellHeader => {
          headers.push(cellHeader.textContent);
        });

        table.querySelectorAll('tbody tr').forEach(tableRow => {
          tableRow.querySelectorAll('td').forEach((dataCell, index) => {
            if (!dataCell.hasAttribute('data-header')) {
              dataCell.setAttribute('data-header', headers[index]);
            }
          });
        });
      });
    }
  };

})(window, document, Drupal);
