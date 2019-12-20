# Collection Subsites

This module allows some collection types to be configured as 'subsites', which are intended to represent distinct sections of a site with custom block layout and branding.

## Unique attributes of subsites

- Custom header
  - Custom logo (custom block: subsite_branding)
  - Custom menu (custom block: subsite_menu)
  - Top main nav banner (with it's own logo, menu, and search)
- Custom footer (optional, defaults to current)
- Color scheme (perhaps via subtheme and union skin)

## Creating a Subsite

### Create a new collection type and select `Contains Subsites`.

### Create a new collection of the new type created above (e.g. Scheinman Institute).

This will automatically (via collection postSave() event dispatch):

  - Create a custom menu for this subsite and add it to the collection.
