# Transactional email (Brevo)

Use a verified Brevo sender and enter credentials securely in the backend environment, never in Git or chat:

- MAIL_MAILER=smtp
- MAIL_HOST=smtp-relay.brevo.com
- MAIL_PORT=587
- MAIL_SCHEME=smtp (STARTTLS is negotiated automatically)
- MAIL_USERNAME: Brevo SMTP login
- MAIL_PASSWORD: Brevo SMTP key (not the account password or API key)
- MAIL_FROM_ADDRESS: verified sender address
- MAIL_FROM_NAME: store name
- FRONTEND_URL: actual storefront origin used in password reset links
- QUEUE_CONNECTION=database

Run `php artisan config:clear` and restart the queue worker after changing settings. Run `php artisan queue:work --tries=3 --backoff=60` continuously. Status emails are queued after the status transaction commits and contain the status at the time of the change. Password reset uses Laravel's expiring, single-use reset tokens; successful resets revoke existing API tokens.

Local development keeps MAIL_MAILER=log. Real delivery requires SMTP network access and valid Brevo credentials. Verify delivery with a test account after configuring these values. No real email has been sent during implementation.
