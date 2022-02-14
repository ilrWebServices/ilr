# Webform UTM Values

This module passes UTM parameter values (e.g. utm_campaign, utm_source) from the persistent_visitor_parameters cookie to a composite field on a webform.

## Usage

Add a _UTM values_ element to any webform.

## How it works

The webform_submission presave hook in this module will set the `utm_values` composite field values if there is a persistent_visitor_parameters cookie with utm_* parameters stored in it.

## Why?

This allows is to track UTM codes from URL query parameters all the way to Salesforce leads. It works like this:

1. A visitor lands on a page like https://example.com?utm_campaign=mygreatcampaign&utm_source=email
2. The persistent_visitor_parameters, as configured, stores those utm_* parameters into a cookie.
3. The visitor navigates to a webform with the _UTM Values_ element.
4. Upon submission, the presave hook stores the persistent_visitor_parameters cookie values into the _UTM values_ field.
5. A Salesforce mapping is configured to map the submission field, via a token like `[webform_submission:values:utm_values:utm_campaign:raw]`, to a Salesforce lead.
