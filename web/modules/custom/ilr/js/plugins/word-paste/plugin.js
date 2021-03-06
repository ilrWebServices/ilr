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
        // Replace non-breaking spaces with regular spaces.
        evt.data.dataValue = evt.data.dataValue.replace(/&nbsp;/ig, ' ');

        // Replace multiple spaces with a single space.
        evt.data.dataValue = evt.data.dataValue.replace(/[ ]{2,}/g, ' ');

        let dom = new DOMParser().parseFromString(evt.data.dataValue, 'text/html');

        // Remove spans. Note that the HTMLCollection returned by
        // getElementsByTagName() is converted into an array. This is so it can
        // be reversed and nested spans removed from the inside out.
        Array.from(dom.getElementsByTagName('span')).reverse().forEach((span) => {
          // See https://stackoverflow.com/a/48573634/14288730
          span.replaceWith(...span.childNodes);
        })

        // Remove paragraphs with no text.
        for (let paragraph of dom.getElementsByTagName('p')) {
          if (!paragraph.innerText) {
            paragraph.parentElement.removeChild(paragraph);
          }
        }

        // Remove anchors with no inner content, but retain any whitespace.
        for (let a of dom.getElementsByTagName('a')) {
          if (!a.innerText.trim()) {
            a.replaceWith(a.innerText);
          }
        }

        // Replace the pasted content with the cleaned up dom.
        evt.data.dataValue = dom.getElementsByTagName('body')[0].innerHTML;
      }, null, null, 9);
    }
  });

})(CKEDITOR);
