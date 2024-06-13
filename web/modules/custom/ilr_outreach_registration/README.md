# ILR Outreach Registration

This module provides integration between webforms and ILR Outreach Salesforce events.

~~It provides a webform handler that posts submissions to a webhook in Salesforce at `/services/apexrest/WebReg`.~~ This was removed in 2024 and replaced with a Touchpoint__c mapping.

Also included is generic event registration webform which can be embedded on external sites. It also has two configurable fields.

## Embedding the form

```
<script src="//www.ilr.cornell.edu/webform/event_registration/share.js"></script>
```

## Configurable fields

### Event ID

This is a hidden, required field which can be pre-populated with the `eventid` query parameter:

```
<script src="//www.ilr.cornell.edu/webform/event_registration/share.js?eventid=a0i4U00000UMjIIQA1"></script>
```

In most cases, `eventid` should be the Salesforce ID for an event/class object.

### Areas of interest

The `interests[]` query parameter can be used to display mailing list opt-in options. For example:

```
<script src="//www.ilr.cornell.edu/webform/event_registration/share.js?eventid=a0i4U00000UMjIIQA1&interests[]=Conflict+Resolution&interests[]=Leadership+and+Organizational+Change"></script>
```

Note the square brackets (`[]`) in the parameter name and `+` characters that replace spaces.

These are all of the possible options for `interests[]`:

```
Conflict+Resolution
Diversity+and+Inclusion
Employee+Relations
Employment+Law
Human+Resources
Labor+Relations
Leadership+and+Organizational+Change
Union+Leadership
```

## Known issues

- Changes to interest params don't appear for anonymous users because of caching issues.
- The `interests[]` query parameter will only work on webform elements with the key `outreach_areas_of_interest`. This should probably become a custom element.
