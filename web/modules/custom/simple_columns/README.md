# Simple Columns

Adds two ckeditor buttons to format text in two (and only two!) columns.

## Usage

Enable this module.

Configure a text format (`/admin/config/content/formats`) and edit the *Toolbar configuration*
 to include the 'Simple Columns' and (optionally) the 'Simple Column Break' buttons.

 When editing text using that editor, select the text that should appear in columns and click the 'Simple Columns' button.

 If using the column break button, place your cursor in an element within the columns that should be placed at the top of the second column and click the 'Simple Column Break' button.

## Known issues

- Column breaks don't work in browsers that don't support `break-before: column;` (e.g. Firefox). In these browsers, a green column break marker will appear above the element that should appear at the top of the second column.
- Multiple column breaks are created when splitting blocks with `.simple-columns--column-break`.

## Roadmap

- Add simple polyfill for browsers that don't support `break-before: column;`. Possibility: Wrap column break element and all siblings in a `div` with `break-inside: avoid;` via javascript.
