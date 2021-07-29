# CollegeNET to Salesforce Grad Lead

This module fetches a CollegeNET prospect report in CSV format via SFTP and creates or updates Grad Leads in Salesforce.

It is triggered via a URL at `/collegenet2sf/endpoint/{cron_key}`. This will fetch the latest CollegeNET CSV export and do the following:

1. Filter out any rows that are missing a `CRM_ID` value. There shouldn't be any, but it's checked just in case.
2. Fetches all 'unlinked' Grad Leads from Salesforce. For our purposes, 'unlinked' means a Grad Lead in Salesforce that has no value for the `CollegeNET_CRM_ID__c` field.
3. Generates a new CSV file with CollegeNET field names mapped to Salesforce field names. This mapping is stored in the `collegenet2sf.settings` config entity. There is no UI for these settings; they can be modified manually.
4. While generating the new CSV file, each record is checked against the unlinked Grad Leads list to see if one exists for the current record email. If so, the Lead Salesforce ID and the `CRM_ID` is added to a `leadsToLink` list for future processing.
5. If there are any Grad Leads to link, the Lead is updated with a value for `CollegeNET_CRM_ID__c`.
6. A Salesforce Bulk API 2.0 upsert job is created with the new CSV data. The `CollegeNET_CRM_ID__c` field is used as an External ID. The Bulk API 2.0 job id is stored in a queue, since Bulk API jobs are run asynchronously on Salesforce and we can't know the results right away.
7. On cron runs, Bulk API jobs in the queue are checked. For complete jobs, the failedResults are fetched and logged if any exist.

The `{cron_key}` value can be found at `/admin/config/system/cron`.
