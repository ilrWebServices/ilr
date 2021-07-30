# CollegeNET to Salesforce Grad Lead

This module adds a service that fetches a CollegeNET prospect report and creates or updates Grad Leads in Salesforce.

It will fetch the latest CollegeNET CSV export via SFTP and do the following:

1. Filter out any rows that are missing a `CRM_ID` value. There shouldn't be any, but it's checked just in case.
2. Fetch all 'unlinked' Grad Leads from Salesforce. For our purposes, 'unlinked' means a Grad Lead in Salesforce that has no value for the `CollegeNET_CRM_ID__c` field.
3. Generate a new CSV file with CollegeNET field names mapped to Salesforce field names. This mapping is stored in the `collegenet2sf.settings` config entity. There is no UI for these settings; they can be modified manually.
4. While generating the new CSV file, check each record against the unlinked Grad Leads list to see if one exists for the current record email. If so, the Lead Salesforce ID and the `CRM_ID` is added to a `leadsToLink` list for future processing.
5. If there are any Grad Leads to link, update each Lead with a value for `CollegeNET_CRM_ID__c`.
6. Create a Salesforce Bulk API 2.0 upsert job with the new CSV data. The `CollegeNET_CRM_ID__c` field is used as an External ID. The Bulk API 2.0 job id is stored in a queue, since Bulk API jobs are run asynchronously on Salesforce and we can't know the results right away.

Later, on normal cron runs, Bulk API jobs in the queue are checked. For complete jobs, the failedResults are fetched and logged if any exist.

## Requirements

A `collegenet` sftp server setting should be configured before use. Place something like the following in `settings.php` or `settings.local.php`.

```
/**
 * Configure sftp servers.
 */
$settings['sftp'] = [
  'collegenet' => [
    'server' => 'sftp.applyweb.com',
    'username' => getenv('COLLEGENET_SFTP_USER'),
    'password' => getenv('COLLEGENET_SFTP_PASSWORD'),
  ],
];
```

## Running the processor

The processor can be triggered in one of two ways:

- The `drush collegenet2sf:run` command.
- A dedicated URL at `/collegenet2sf/endpoint/{cron_key}`. The `{cron_key}` value can be found at `/admin/config/system/cron`.

The intention of the URL is to allow the processor to be run by trusted individuals without them needing remote drush access.
