/**
 * @file
 * Simple Columns plugin.
 *
 * @ignore
 */

(function ($, Drupal, CKEDITOR) {

  let hasColumnParent = function(element) {
    if ((element instanceof CKEDITOR.dom.element) && element.getComputedStyle('column-count') === '2') {
      return element;
    } else {
      // Search up the parent element to see if the selection in already in a column div.
      // let columnParent = selectionParentElement.getAscendant('div');
      return element.getAscendant(function (el) {
        return (el instanceof CKEDITOR.dom.element) && el.getComputedStyle('column-count') === '2';
      });
    }
  };

  CKEDITOR.plugins.add('simplecolumns', {
    icons: 'simplecolumns',
    hidpi: false,

    init: function (editor) {
      editor.addCommand('toggleColumns', {
        contextSensitive: 1,
        exec: function (editor) {
          let selection = editor.getSelection();
          let columnParent = hasColumnParent(selection.getCommonAncestor());

          if (columnParent) {
            // Remove column wrapper while preserving its contents.
            columnParent.remove(true);
            this.setState(CKEDITOR.TRISTATE_OFF);
          } else {
            // Wrap the elements in a selection with a column div.
            let ranges = selection.getRanges();
            let startPath, endPath, startElement, endElement;

            for (let i = 0; i < ranges.length; i++) {
              startPath = ranges[i].startPath();
              endPath = ranges[i].endPath();

              // Ensure that the elements at the start and end of the selection
              // are siblings.
              if (startPath.elements.length === endPath.elements.length) {
                startElement = startPath.block;
                endElement = endPath.block;
              } else if (startPath.elements.length < endPath.elements.length) {
                startElement = startPath.block;
                endElement = endPath.elements[endPath.elements.length - startPath.elements.length];
              } else {
                startElement = startPath.elements[startPath.elements.length - endPath.elements.length];
                endElement = endPath.block;
              }
            }

            let columnWrapper = new CKEDITOR.dom.element('div').setStyle('column-count', '2');
            let nextElement = startElement;
            let elements = [nextElement];

            // Put all of the sibling elements between the selection start and
            // end into an array. For some reason, the following doesn't work:
            // while (!nextElement.isIdentical(endElement)) {
            while (nextElement.$ !== endElement.$) {
              nextElement = nextElement.getNext();
              elements.push(nextElement);
            }

            // Move the selected elements into the new column wrapper. This
            // should also pull them out of the dom.
            for (let i = 0; i < elements.length; i++) {
              columnWrapper.append(elements[i]);
            }

            // Place the column, with the selected contents, into the editor.
            editor.insertElement(columnWrapper);
          }
        },

        refresh: function (editor, path) {
          // Set the button to 'on' when the cursor is in a column wrapper.
          if (hasColumnParent(path.lastElement)) {
            this.setState(CKEDITOR.TRISTATE_ON);
          } else {
            this.setState(CKEDITOR.TRISTATE_OFF);
          }
        }
      });

      editor.ui.addButton('SimpleColumns', {
        label: 'Simple Columns',
        command: 'toggleColumns',
        toolbar: 'insert'
      });
    }
  });

})(jQuery, Drupal, CKEDITOR);
