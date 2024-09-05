/**
 * @file defines InsertSimpleBoxCommand, which is executed when the simpleBox
 * toolbar button is pressed.
 */
// cSpell:ignore simpleboxediting

import { Command } from 'ckeditor5/src/core';

export default class InsertSimpleBoxCommand extends Command {
  execute() {
    const { model } = this.editor;

    model.change((writer) => {
      // Insert <simpleBox>*</simpleBox> at the current selection position
      // in a way that will result in creating a valid model structure.
      model.insertContent(createSimpleBox(writer));
    });
  }

  refresh() {
    const { model } = this.editor;
    const { selection } = model.document;

    // Determine if the cursor (selection) is in a position where adding a
    // simpleBox is permitted. This is based on the schema of the model(s)
    // currently containing the cursor.
    const allowedIn = model.schema.findAllowedParent(
      selection.getFirstPosition(),
      'simpleBox',
    );

    // If the cursor is not in a location where a simpleBox can be added, return
    // null so the addition doesn't happen.
    this.isEnabled = allowedIn !== null;
  }
}

function createSimpleBox(writer) {
  // Create instances of the three elements registered with the editor in
  // simpleboxediting.js.
  const simpleBox = writer.createElement('simpleBox');
  const simpleBoxTitle = writer.createElement('simpleBoxTitle');
  const simpleBoxDescription = writer.createElement('simpleBoxDescription');

  // Append the title and description elements to the simpleBox, which matches
  // the parent/child relationship as defined in their schemas.
  writer.append(simpleBoxTitle, simpleBox);
  writer.append(simpleBoxDescription, simpleBox);

  // The simpleBoxDescription text content will automatically be wrapped in a
  // `<p>`.
  writer.appendElement('paragraph', simpleBoxDescription);

  // Return the element to be added to the editor.
  return simpleBox;
}
