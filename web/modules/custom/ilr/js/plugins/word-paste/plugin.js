/**
 * @file plugin.js
 *
 * Plugin for paste from Word enhancements for the CKEditor.
 */
 (function(CKEDITOR) {

  CKEDITOR.plugins.add('ilr_word_paste', {
    icons: 'word-paste',
    init: function(editor) {
      // Paste from clipboard:
      editor.on('afterPasteFromWord', function(evt) {
        let dom = new DOMParser().parseFromString(evt.data.dataValue, 'text/html');
        let paragraphs = dom.getElementsByTagName('p');

        // Remove paragraphs with no text.
        for (let paragraph of paragraphs) {
          if (!paragraph.innerText) {
            paragraph.parentElement.removeChild(paragraph);
          }
        }

        // Replace the pasted content with the cleaned up dom.
        evt.data.dataValue = dom.getElementsByTagName('body')[0].innerHTML;
      }, null, null, 9);
    }
  });

})(CKEDITOR);
