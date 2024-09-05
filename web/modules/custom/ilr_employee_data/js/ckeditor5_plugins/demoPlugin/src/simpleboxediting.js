import { Plugin } from 'ckeditor5/src/core';
import { toWidget, toWidgetEditable } from 'ckeditor5/src/widget';
import { Widget } from 'ckeditor5/src/widget';
import InsertSimpleBoxCommand from './insertsimpleboxcommand';

// cSpell:ignore simplebox insertsimpleboxcommand

/**
 * CKEditor 5 plugins do not work directly with the DOM. They are defined as
 * plugin-specific data models that are then converted to markup that
 * is inserted in the DOM.
 *
 * CKEditor 5 internally interacts with simpleBox as this model:
 * <simpleBox>
 *    <simpleBoxTitle></simpleBoxTitle>
 *    <simpleBoxDescription></simpleBoxDescription>
 * </simpleBox>
 *
 * Which is converted for the browser/user as this markup
 * <section class="simple-box">
 *   <h2 class="simple-box-title"></h1>
 *   <div class="simple-box-description"></div>
 * </section>
 *
 * This file has the logic for defining the simpleBox model, and for how it is
 * converted to standard DOM markup.
 */
export default class SimpleBoxEditing extends Plugin {
  static get requires() {
    return [Widget];
  }

  init() {
    this._defineSchema();
    this._defineConverters();
    this.editor.commands.add(
      'insertSimpleBox',
      new InsertSimpleBoxCommand(this.editor),
    );
  }

  /*
   * This registers the structure that will be seen by CKEditor 5 as
   * <simpleBox>
   *    <simpleBoxTitle></simpleBoxTitle>
   *    <simpleBoxDescription></simpleBoxDescription>
   * </simpleBox>
   *
   * The logic in _defineConverters() will determine how this is converted to
   * markup.
   */
  _defineSchema() {
    // Schemas are registered via the central `editor` object.
    const schema = this.editor.model.schema;

    schema.register('simpleBox', {
      // Behaves like a self-contained object (e.g. an image).
      isObject: true,
      // Allow in places where other blocks are allowed (e.g. directly in the root).
      allowWhere: '$block',
    });

    schema.register('simpleBoxTitle', {
      // This creates a boundary for external actions such as clicking and
      // and keypress. For example, when the cursor is inside this box, the
      // keyboard shortcut for "select all" will be limited to the contents of
      // the box.
      isLimit: true,
      // This is only to be used within simpleBox.
      allowIn: 'simpleBox',
      // Allow content that is allowed in blocks (e.g. text with attributes).
      allowContentOf: '$block',
    });

    schema.register('simpleBoxDescription', {
      isLimit: true,
      allowIn: 'simpleBox',
      allowContentOf: '$root',
    });

    schema.addChildCheck((context, childDefinition) => {
      // Disallow simpleBox inside simpleBoxDescription.
      if (
        context.endsWith('simpleBoxDescription') &&
        childDefinition.name === 'simpleBox'
      ) {
        return false;
      }
    });
  }

  /**
   * Converters determine how CKEditor 5 models are converted into markup and
   * vice-versa.
   */
  _defineConverters() {
    // Converters are registered via the central editor object.
    const { conversion } = this.editor;

    // Upcast Converters: determine how existing HTML is interpreted by the
    // editor. These trigger when an editor instance loads.
    //
    // If <section class="simplebox"> is present in the existing markup
    // processed by CKEditor, then CKEditor recognizes and loads it as a
    // <simpleBox> model.
    conversion.for('upcast').elementToElement({
      model: 'simpleBox',
      view: {
        name: 'section',
        classes: 'simple-box',
      },
    });

    // If <h2 class="simple-box-title"> is present in the existing markup
    // processed by CKEditor, then CKEditor recognizes and loads it as a
    // <simpleBoxTitle> model, provided it is a child element of <simpleBox>,
    // as required by the schema.
    conversion.for('upcast').elementToElement({
      model: 'simpleBoxTitle',
      view: {
        name: 'h2',
        classes: 'simple-box-title',
      },
    });

    // If <h2 class="simple-box-description"> is present in the existing markup
    // processed by CKEditor, then CKEditor recognizes and loads it as a
    // <simpleBoxDescription> model, provided it is a child element of
    // <simpleBox>, as required by the schema.
    conversion.for('upcast').elementToElement({
      model: 'simpleBoxDescription',
      view: {
        name: 'div',
        classes: 'simple-box-description',
      },
    });

    // Data Downcast Converters: converts stored model data into HTML.
    // These trigger when content is saved.
    //
    // Instances of <simpleBox> are saved as
    // <section class="simple-box">{{inner content}}</section>.
    conversion.for('dataDowncast').elementToElement({
      model: 'simpleBox',
      view: {
        name: 'section',
        classes: 'simple-box',
      },
    });

    // Instances of <simpleBoxTitle> are saved as
    // <h2 class="simple-box-title">{{inner content}}</h2>.
    conversion.for('dataDowncast').elementToElement({
      model: 'simpleBoxTitle',
      view: {
        name: 'h2',
        classes: 'simple-box-title',
      },
    });

    // Instances of <simpleBoxDescription> are saved as
    // <div class="simple-box-description">{{inner content}}</div>.
    conversion.for('dataDowncast').elementToElement({
      model: 'simpleBoxDescription',
      view: {
        name: 'div',
        classes: 'simple-box-description',
      },
    });

    // Editing Downcast Converters. These render the content to the user for
    // editing, i.e. this determines what gets seen in the editor. These trigger
    // after the Data Upcast Converters, and are re-triggered any time there
    // are changes to any of the models' properties.
    //
    // Convert the <simpleBox> model into a container widget in the editor UI.
    conversion.for('editingDowncast').elementToElement({
      model: 'simpleBox',
      view: (modelElement, { writer: viewWriter }) => {
        const section = viewWriter.createContainerElement('section', {
          class: 'simple-box',
        });

        return toWidget(section, viewWriter, { label: 'simple box widget' });
      },
    });

    // Convert the <simpleBoxTitle> model into an editable <h2> widget.
    conversion.for('editingDowncast').elementToElement({
      model: 'simpleBoxTitle',
      view: (modelElement, { writer: viewWriter }) => {
        const h2 = viewWriter.createEditableElement('h2', {
          class: 'simple-box-title',
        });
        return toWidgetEditable(h2, viewWriter);
      },
    });

    // Convert the <simpleBoxDescription> model into an editable <div> widget.
    conversion.for('editingDowncast').elementToElement({
      model: 'simpleBoxDescription',
      view: (modelElement, { writer: viewWriter }) => {
        const div = viewWriter.createEditableElement('div', {
          class: 'simple-box-description',
        });
        return toWidgetEditable(div, viewWriter);
      },
    });
  }
}
