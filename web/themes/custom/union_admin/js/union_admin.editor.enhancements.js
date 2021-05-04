(function (CKEDITOR) {

  CKEDITOR.on('instanceReady', function(ev) {
    ev.editor.on('afterPasteFromWord', function(evt) {
      let dom = new DOMParser().parseFromString(evt.data.dataValue, 'text/html');
      let paragraphs = dom.getElementsByTagName('p');

      // Remove paragraphs with no text.
      for (let paragraph of paragraphs) {
        if (!paragraph.innerText) {
          paragraph.parentElement.removeChild(paragraph);
        }
      }

      evt.data.dataValue = dom.getElementsByTagName('body')[0].innerHTML;
    }, null, null, 9);
  });

})(CKEDITOR);
