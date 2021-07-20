# CollegeNET to Salesforce Lead

This module fetches a CollegeNET prospect report in CSV format via SFTP and creates or updates MILR Leads in Salesforce.

It is triggered via a URL at /collegenet2sf/milr. This will add the latest export to a queue that will be processed via cron.
