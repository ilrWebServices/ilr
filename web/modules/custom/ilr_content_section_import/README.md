# ILR Content Section Import

This module imports content from a custom JSON file (see sample.json) into nodes that are placed into a Content Section collection.

The idea is to import content for a single section of the site, e.g., the student experience section.

There are a few reasons this custom code is used instead of a Drupal migration:

- Moving the data into our nested paragraph structure (e.g. node > section paragraph > text paragraphs) is complex and unfun.
- Migrate could make it tricky to place nodes into our Content Section collections.
- It's way easier to grok what this small, custom module is doing than it is to debug migrations.

A migration is still an option if this approach doesn't work.
