# Collection

Organize any content or configuration entity into an arbitrary collection.

## Listings

Currently, sites using the paragraphs module can create filtered listings of items in a collection. To enable this feature, simply add a collection entity reference field to a paragraph type, and then enable the collection listing behavior for that type.

When adding a paragraph item of this type, there will be a new "Behaviors" tab in the UI that allows you to specify the content types, bundles and display modes to include when that listing is rendered, as well as the number of items.

### Required Patches for Listings

https://www.drupal.org/files/issues/core-patch-2915512-7.patch
https://www.drupal.org/files/issues/2020-03-19/3120952-2.patch

## Roadmap

- Customize DER allowed referenced per collection or collection item type. Research how node module allows base field overrides to be stored in separate config items. See https://drupal.stackexchange.com/questions/253257/how-to-easily-alter-an-entitys-base-field-definition-per-bundle
- Implement user interface for adding items to collection (e.g. contextual links, custom block, node add/edit form).
- Add 'in collection' condition and admin block (maybe).
- Implement exposed filters in the collection item listing. Investigate using Views as the listbuilder.
- Allow bulk operations on collection item lists.
- Fix Views integration.
- Add collection item listing to collection pages.
- Improve collection permissions
  - per collection type add/edit/delete
- Offer to remove collection items when deleting a collection
  - Or prevent deletion of collections with items
- Add tests
