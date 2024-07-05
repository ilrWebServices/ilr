## INTRODUCTION

The Taxonomy Unique Name module adds a setting to vocabularies that, when 
enabled, prevents duplicate term names.

This is the exact same functionality as the `taxonomy_unique` module, but in my
testing, that module did not work with Inline Entity Form fields. Turns out
that this one didn't either, at first. After upgrading IEF to 3.x, it did start
working, and I wonder if `taxonomy_unique` would have, too.

Differences with `taxonomy_unique`:

- Stores configuration as third party settings on Vocabulary config entities
  rather than a custom config object.
- Saves config via `#entity_builders` rather than a `#submit` handler.
- Does not validate auto-created terms (`@EntityReferenceSelection` plugin).
- No tests!

## INSTALLATION

Install as you would normally install a contributed Drupal module.
See: https://www.drupal.org/node/895232 for further information.

## CONFIGURATION

Edit a given vocabulary and check the 'Force unique names' checkbox.

## MAINTAINERS

Current maintainers for Drupal 10:

- Jeff Amaral (jeffam) - https://www.drupal.org/u/jeffam

