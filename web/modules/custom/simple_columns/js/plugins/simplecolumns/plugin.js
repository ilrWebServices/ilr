/**
 * @file
 * Simple Columns plugin.
 *
 * @ignore
 */

(function ($, Drupal, CKEDITOR) {

  let hasColumnParent = function(element) {
    if ((element instanceof CKEDITOR.dom.element) && element.hasClass('simple-columns')) {
      return element;
    } else {
      // Search up the parent element to see if the selection in already in a column div.
      // let columnParent = selectionParentElement.getAscendant('div');
      return element.getAscendant(function (el) {
        return (el instanceof CKEDITOR.dom.element) && el.hasClass('simple-columns');
      });
    }
  };

  CKEDITOR.plugins.add('simplecolumns', {
    icons: 'simplecolumns,simplecolumnbreak',
    hidpi: false,

    init: function (editor) {
      editor.addCommand('toggleColumns', {
        contextSensitive: 1,
        exec: function (editor) {
          let selection = editor.getSelection();
          let columnParent = hasColumnParent(selection.getCommonAncestor());

          if (columnParent) {
            // Remove any column breaks on child elements.
            let children = columnParent.getChildren();
            for (let i = 0; i < children.count(); i++) {
              children.getItem(i).removeClass('simple-columns--column-break')
            }

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

            let columnWrapper = new CKEDITOR.dom.element('div').addClass('simple-columns');
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

      editor.addCommand('addColumnBreak', {
        contextSensitive: 1,
        startDisabled: 1,

        exec: function (editor) {
          let selection = editor.getSelection();
          let startElement = selection.getStartElement();
          let isColumnChild = startElement.getParent().hasClass('simple-columns');
          let siblings;

          // Only elements with an immediate column wrapper parent can get the column break class.
          if (isColumnChild) {
            if (startElement.hasClass('simple-columns--column-break')) {
              startElement.removeClass('simple-columns--column-break');
              this.setState(CKEDITOR.TRISTATE_OFF);
            } else {
              siblings = startElement.getParent().getChildren();

              for (let i = 0; i < siblings.count(); i++) {
                if (siblings.getItem(i).hasClass('simple-columns--column-break')) {
                  siblings.getItem(i).removeClass('simple-columns--column-break');
                }
              }

              startElement.addClass('simple-columns--column-break');
              this.setState(CKEDITOR.TRISTATE_ON);
            }
          }
        },

        refresh: function (editor, path) {
          // Set the button to 'on' when the cursor is in a column wrapper.
          if (path.lastElement.hasClass('simple-columns--column-break')) {
            this.setState(CKEDITOR.TRISTATE_ON);
          } else if (path.lastElement.getParent().hasClass('simple-columns')) {
            this.setState(CKEDITOR.TRISTATE_OFF);
          } else {
            this.setState(CKEDITOR.TRISTATE_DISABLED);
          }
        }
      });

      editor.ui.addButton('SimpleColumns', {
        label: 'Simple Columns',
        command: 'toggleColumns'
      });

      editor.ui.addButton('SimpleColumnBreak', {
        label: 'Column Break',
        command: 'addColumnBreak'
      });
    }
  });

})(jQuery, Drupal, CKEDITOR);
