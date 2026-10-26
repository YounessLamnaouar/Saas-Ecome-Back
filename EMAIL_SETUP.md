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

## Newsletter

The footer subscribes contacts to a Brevo list through the backend. Configure `BREVO_API_KEY` (Brevo API v3 key, separate from your SMTP key) and `BREVO_NEWSLETTER_LIST_ID` (the numeric ID of the newsletter list) securely in the backend environment. Allow outbound HTTPS to `api.brevo.com`. The browser never receives the key. Subscriptions report success only after Brevo accepts the contact; repeated subscriptions update the existing contact without duplication. Existing unsubscribe/blacklist flags are not reset.

## Order status notifications

Confirmed, out-for-delivery and delivered transitions queue an email containing the order code and new status. Keep `php artisan queue:work --tries=3 --backoff=60` running. Use `php artisan queue:failed` to inspect failures and `php artisan queue:retry ID` to retry a specific failed notification after fixing delivery settings.

## Demo catalog with real product images

Run `php artisan catalog:import-dummyjson` after pulling. It imports products from `https://dummyjson.com/products` into Laravel so stock, checkout and admin edits remain persistent. It preserves existing products and does not reset stock on repeated runs. Fresh database seeding now uses this importer. Allow `dummyjson.com` for import and `cdn.dummyjson.com` for browser images. Existing demo products are retained to avoid breaking historical order references.
