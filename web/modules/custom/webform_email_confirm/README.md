# Webform Email Confirm

Allows webforms with email address fields to send email confirmation messages to ensure that the email entered in the form is owned by the submitter.

1. User fills out form with `email` field.
2. A normal webform submission is created, but its `email_confirmation_status` is set to '' (empty string) or `0`.
3. An expiring key/value record (SharedTempStore) is stored with a new random token and reference to the webform submission.
4. User receives email with confirmation link. E.g. www.example.edu/webform_email_confirm/{token}
5. If the hash matches a known pending & unexpired confirmation key/value record, set `email_confirmation_status` to `TRUE` and immediately remove the key/value record.
6. Fire an event? Or just rely on submission update hooks?

## Campaign Monitor Transactional Emails

Email will be sent using the standard Drupal email pipeline. To send via Campaign Monitor Transactional emails, we should implement a mail plugin (`cm_transactional_mail`) and update `system.mail.interface` to use it just for `webform_email_confirm` messages. See https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Mail%21MailManager.php/function/MailManager%3A%3AgetInstance/9.4.x

